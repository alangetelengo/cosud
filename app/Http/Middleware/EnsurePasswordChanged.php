<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Oblige le changement de mot de passe temporaire avant d’accéder à l’application.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(
            'password.force-change',
            'password.force-change.update',
            'logout',
        )) {
            return $next($request);
        }

        return redirect()->route('password.force-change');
    }
}
