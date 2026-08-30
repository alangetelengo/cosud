<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserAffectationController extends Controller
{
    public function index(User $user)
    {
        $this->authorize('update', $user);

        return redirect()
            ->route('utilisateurs.edit', $user)
            ->withFragment('structures');
    }

    public function store(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'structure_id' => ['required', 'exists:structures,id'],
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'role' => ['nullable', 'string', 'max:50'],
            'date_affectation' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        if ($user->structures()->where('structures.id', $validated['structure_id'])->exists()) {
            return redirect()
                ->route('utilisateurs.edit', $user)
                ->withFragment('structures')
                ->withInput()
                ->with('error', 'Cet utilisateur est déjà affecté à cette structure. Modifiez la ligne existante ou supprimez-la avant d’en ajouter une nouvelle.');
        }

        $user->structures()->attach($validated['structure_id'], [
            'fonction_id' => filled($validated['fonction_id'] ?? null) ? (int) $validated['fonction_id'] : null,
            'role' => filled($validated['role'] ?? null) ? $validated['role'] : null,
            'date_affectation' => filled($validated['date_affectation'] ?? null) ? $validated['date_affectation'] : now(),
            'date_fin' => filled($validated['date_fin'] ?? null) ? $validated['date_fin'] : null,
        ]);

        JournalAudit::log('utilisateur.affectation_structure', 'utilisateurs', [
            'user_id' => $user->id,
            'structure_id' => $validated['structure_id'],
        ]);
        Log::channel('cosud')->info('Affectation structure utilisateur', ['user_id' => $user->id, 'structure_id' => $validated['structure_id'], 'by' => auth()->id()]);

        return redirect()
            ->route('utilisateurs.edit', $user)
            ->withFragment('structures')
            ->with('success', 'Affectation ajoutée.');
    }

    public function update(Request $request, User $user, Structure $structure)
    {
        $this->authorize('update', $user);

        if (! $user->structures()->where('structures.id', $structure->id)->exists()) {
            abort(404);
        }

        $validated = $request->validate([
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'role' => ['nullable', 'string', 'max:50'],
            'date_affectation' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        $currentPivot = $user->structures()->where('structures.id', $structure->id)->first()->pivot;

        $user->structures()->updateExistingPivot($structure->id, [
            'fonction_id' => filled($validated['fonction_id'] ?? null) ? (int) $validated['fonction_id'] : null,
            'role' => filled($validated['role'] ?? null) ? $validated['role'] : null,
            'date_affectation' => filled($validated['date_affectation'] ?? null)
                ? $validated['date_affectation']
                : ($currentPivot->date_affectation ?? now()),
            'date_fin' => filled($validated['date_fin'] ?? null) ? $validated['date_fin'] : null,
        ]);

        JournalAudit::log('utilisateur.affectation_structure_modif', 'utilisateurs', [
            'user_id' => $user->id,
            'structure_id' => $structure->id,
        ]);
        Log::channel('cosud')->info('Affectation structure mise à jour', ['user_id' => $user->id, 'structure_id' => $structure->id, 'by' => auth()->id()]);

        return redirect()
            ->route('utilisateurs.edit', $user)
            ->withFragment('structures')
            ->with('success', 'Affectation mise à jour.');
    }

    public function destroy(User $user, Structure $structure)
    {
        $this->authorize('update', $user);

        if (! $user->structures()->where('structures.id', $structure->id)->exists()) {
            abort(404);
        }

        $user->structures()->detach($structure->id);

        if ((int) $user->structure_id === (int) $structure->id) {
            $user->update(['structure_id' => null]);
        }

        JournalAudit::log('utilisateur.affectation_structure_suppr', 'utilisateurs', [
            'user_id' => $user->id,
            'structure_id' => $structure->id,
        ]);
        Log::channel('cosud')->info('Affectation structure retirée', ['user_id' => $user->id, 'structure_id' => $structure->id, 'by' => auth()->id()]);

        return redirect()
            ->route('utilisateurs.edit', $user)
            ->withFragment('structures')
            ->with('success', 'Affectation retirée.');
    }
}
