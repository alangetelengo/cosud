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
}
