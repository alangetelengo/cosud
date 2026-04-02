<?php

namespace App\Policies;

use App\Models\Dossier;
use App\Models\User;

class DossierPolicy
{
    /**
     * Peut accéder au formulaire de création : sous-dossier (create-structure) ou racine de structure (create-racine-structure).
     */
    public function create(User $user): bool
    {
        if ($user->can('dossiers.create-structure')
            || $user->can('dossiers.create-racine-structure')) {
            return true;
        }

        // Tolérance métier: un utilisateur peut toujours créer un sous-dossier
        // dans son propre arbre "Mes dossiers" déjà existant.
        return Dossier::query()
            ->where('racine_utilisateur_id', $user->id)
            ->exists();
    }

    /** Vérifie que l'utilisateur peut créer un dossier sous le parent donné (structure autorisée ou arbre « Mes dossiers »). */
    public function createInParent(User $user, ?int $parentId, ?int $structureId): bool
    {
        if (! $user->can('dossiers.create-structure')) {
            return false;
        }
        $allowedIds = $this->structureIdsAutorisees($user);
        $racine = Dossier::where('racine_utilisateur_id', $user->id)->first();

        if ($parentId !== null) {
            $parent = Dossier::find($parentId);
            if (! $parent || ! $parent->visiblePar($user)) {
                return false;
            }
            if ($racine && Dossier::estSousRacineMesDossiers($parent, $racine)) {
                if ($structureId !== null) {
                    $sidParent = $parent->structure_id ?? $parent->structure_id_depot;
                    $attendu = $sidParent ?: $user->structure_id;
                    if ($attendu !== null && (int) $structureId !== (int) $attendu) {
                        return false;
                    }
                }

                return true;
            }
            if (empty($allowedIds)) {
                return false;
            }
            $parentStructureId = $parent->structure_id ?? $parent->structure_id_depot;
            if (! $parentStructureId || ! in_array($parentStructureId, $allowedIds, true)) {
                return false;
            }
        } elseif (empty($allowedIds)) {
            return false;
        }
        if ($structureId !== null && ! in_array($structureId, $allowedIds, true)) {
            return false;
        }

        return true;
    }

    private function structureIdsAutorisees(User $user): array
    {
        return array_filter(array_unique(array_merge(
            [$user->structure_id],
            $user->structureIdsGerees()
        )));
    }

    public function viewAny(User $user): bool
    {
        return $user->can('dossiers.view');
    }

    public function view(User $user, Dossier $dossier): bool
    {
        if (! $user->can('dossiers.view')) {
            return false;
        }
        if (! $dossier->visiblePar($user)) {
            return false;
        }
        if ($dossier->confidentiel && ! $user->can('dossiers.view-confidentiel') && ! $user->aAccesTotal()) {
            // Le créateur / propriétaire doit pouvoir ouvrir le dossier qu'il vient de créer (sinon 403 sur la redirection show).
            if ((int) $dossier->createur_id !== (int) $user->id && (int) $dossier->proprietaire_id !== (int) $user->id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gérer les partages sur ce dossier.
     * Règle métier: seul le propriétaire, le créateur, ou un admin/DG peut partager.
     */
    public function share(User $user, Dossier $dossier): bool
    {
        if (! $user->can('dossiers.view') || ! $dossier->visiblePar($user)) {
            return false;
        }
        if ($user->aAccesTotal()) {
            return true;
        }

        return (int) $dossier->proprietaire_id === (int) $user->id
            || (int) $dossier->createur_id === (int) $user->id;
    }

    /** Métadonnées du dossier (nom, type, description) : aligné sur les mêmes acteurs que le partage direction / propriétaire. */
    public function update(User $user, Dossier $dossier): bool
    {
        if ($this->view($user, $dossier) && (int) $dossier->proprietaire_id === (int) $user->id) {
            return true;
        }

        if (! $user->can('dossiers.edit')) {
            return false;
        }

        return $this->view($user, $dossier) && $this->peutGererMetadonneesDossier($user, $dossier);
    }

    public function delete(User $user, Dossier $dossier): bool
    {
        if (! $user->can('dossiers.delete')) {
            return false;
        }
        if ($dossier->racine_utilisateur_id !== null && ! $user->aAccesTotal()) {
            return false;
        }

        return $this->view($user, $dossier) && $this->peutGererMetadonneesDossier($user, $dossier);
    }

    private function peutGererMetadonneesDossier(User $user, Dossier $dossier): bool
    {
        if ($user->aAccesTotal()) {
            return true;
        }
        if ((int) $dossier->proprietaire_id === (int) $user->id || (int) $dossier->createur_id === (int) $user->id) {
            return true;
        }
        if ($dossier->estSoumisReglePartageTitulaireDirection()) {
            return $user->peutPartagerDossierDirection($dossier);
        }

        return false;
    }
}
