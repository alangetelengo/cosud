<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcePasswordChangeController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()?->must_change_password) {
            return redirect()->route('home');
        }

        return view('auth.force-password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->must_change_password) {
            return redirect()->route('home');
        }

        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ], [
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        Log::channel('cosud')->info('Mot de passe défini à la première connexion', [
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('home')
            ->with('success', 'Votre mot de passe a été enregistré. Bienvenue dans COSUD.');
    }
}
