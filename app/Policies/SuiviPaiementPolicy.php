<?php

namespace App\Policies;

use App\Models\SuiviPaiement;
use App\Models\User;

class SuiviPaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suivi-paiements.view');
    }

    public function view(User $user, SuiviPaiement $suiviPaiement): bool
    {
        return $user->can('suivi-paiements.view');
    }

    /**
     * Saisie d’une dépense hors circuit (remise DG → Eleni).
     */
    public function create(User $user): bool
    {
        if ($user->can('suivi-paiements.create')) {
            return true;
        }

        return $user->hasRole('admin')
            || $user->hasRole('dg')
            || $user->hasRole('responsable_suivi_depenses');
    }

    /**
     * Classement dossier pour les saisies Eleni uniquement.
     * Les factures prestataires (circuit chèque) restent le périmètre de Mme Taty via le courrier.
     */
    public function classerDossier(User $user, SuiviPaiement $suiviPaiement): bool
    {
        if (! $this->view($user, $suiviPaiement)) {
            return false;
        }

        if ($suiviPaiement->estClassementReserveFacturesPrestataires()) {
            return $user->hasRole('admin');
        }

        return $user->hasRole('admin')
            || $user->hasRole('responsable_suivi_depenses')
            || $user->can('suivi-paiements.create');
    }
}
