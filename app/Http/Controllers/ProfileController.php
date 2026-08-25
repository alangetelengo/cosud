<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $structureChemin = null;
        $structurePrincipale = null;

        if ($user->structure_id) {
            $structurePrincipale = Structure::query()->where('id', $user->structure_id)->where('actif', true)->first();
            if ($structurePrincipale) {
                $noms = [];
                $cursor = $structurePrincipale;
                $garde = 0;
                while ($cursor && $garde++ < 30) {
                    array_unshift($noms, $cursor->nom);
                    $cursor = $cursor->parent_id ? Structure::query()->where('id', $cursor->parent_id)->first() : null;
                }
                $structureChemin = implode(' → ', $noms);
            }
        }

        return view('profile.edit', [
            'user' => $user,
            'structurePrincipale' => $structurePrincipale,
            'structureChemin' => $structureChemin,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Log::channel('cosud')->info('Profil mis à jour', ['user_id' => $request->user()->id]);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Log::channel('cosud')->info('Compte supprimé', ['user_id' => $user->id, 'email' => $user->email]);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
