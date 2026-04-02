<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /**
     * Afficher la page de vérification 2FA.
     * Accessible soit après login (session 2fa:user:id) soit si connecté avec 2FA non vérifiée cette session.
     */
    public function showVerify(): View|RedirectResponse
    {
        $user = null;
        $userId = session('2fa:user:id');
        if ($userId) {
            $user = User::find($userId);
        } elseif (Auth::check()) {
            $user = Auth::user();
        }

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            session()->forget(['2fa:user:id', '2fa:remember']);

            return $userId ? redirect()->route('login')->with('error', 'Session expirée.') : redirect('/');
        }

        return view('auth.two-factor-verify');
    }

    /**
     * Vérifier le code TOTP.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ], ['code.regex' => 'Le code TOTP doit contenir 6 chiffres.']);

        $user = null;
        $userId = session('2fa:user:id');
        if ($userId) {
            $user = User::find($userId);
        } elseif (Auth::check()) {
            $user = Auth::user();
        }

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            session()->forget(['2fa:user:id', '2fa:remember']);

            return redirect()->route('login')->with('error', 'Session expirée. Veuillez vous reconnecter.');
        }

        $code = preg_replace('/\s+/', '', $request->code);

        if ($user->verifyTwoFactorCode($code)) {
            $user->markTwoFactorVerified();
            $remember = session('2fa:remember', false);
            session()->forget(['2fa:user:id', '2fa:remember']);
            if (! Auth::check()) {
                Auth::login($user, $remember);
                $request->session()->regenerate();
            }
            $request->session()->put('2fa_verified', true);

            return redirect()->intended(route('home', absolute: false));
        }

        throw ValidationException::withMessages([
            'code' => 'Le code de vérification est invalide.',
        ]);
    }

    /**
     * Vérifier un code de récupération.
     */
    public function verifyRecovery(Request $request): RedirectResponse
    {
        $request->validate([
            'recovery_code' => ['required', 'string', 'size:8'],
        ]);

        $user = null;
        $userId = session('2fa:user:id');
        if ($userId) {
            $user = User::find($userId);
        } elseif (Auth::check()) {
            $user = Auth::user();
        }

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            session()->forget(['2fa:user:id', '2fa:remember']);

            return redirect()->route('login')->with('error', 'Session expirée. Veuillez vous reconnecter.');
        }

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $request->recovery_code));

        if ($user->useRecoveryCode($code)) {
            $user->markTwoFactorVerified();
            $remember = session('2fa:remember', false);
            session()->forget(['2fa:user:id', '2fa:remember']);
            if (! Auth::check()) {
                Auth::login($user, $remember);
                $request->session()->regenerate();
            }
            $request->session()->put('2fa_verified', true);

            return redirect()->intended(route('home', absolute: false))->with('success', 'Connexion réussie avec code de récupération.');
        }

        return back()->withErrors(['recovery_code' => 'Code de récupération invalide ou déjà utilisé.']);
    }

    /**
     * Annuler la vérification 2FA et rediriger vers la connexion.
     * Utilisé quand l'utilisateur est bloqué sur la page 2FA (session partielle ou 2fa_verified expiré).
     */
    public function cancel(Request $request): RedirectResponse
    {
        session()->forget(['2fa:user:id', '2fa:remember', '2fa_verified']);
        if (Auth::check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
