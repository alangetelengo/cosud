<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    /**
     * Si l'utilisateur est connecté avec 2FA activée mais n'a pas encore vérifié cette session,
     * rediriger vers la page de vérification 2FA.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->session()->get('2fa_verified') === true) {
            return $next($request);
        }

        return redirect()->route('two-factor.verify');
    }
}
