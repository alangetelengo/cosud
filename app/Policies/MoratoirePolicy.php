<?php

namespace App\Policies;

use App\Models\Moratoire;
use App\Models\User;

class MoratoirePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('moratoires.view');
    }

    public function view(User $user, Moratoire $moratoire): bool
    {
        // Plans réservés à Eleni / DG (create) — Taty a seulement la vue des dettes.
        return $user->can('moratoires.create') || $user->aAccesTotal();
    }

    public function create(User $user): bool
    {
        return $user->can('moratoires.create');
    }

    public function update(User $user, Moratoire $moratoire): bool
    {
        return $user->can('moratoires.update') && $moratoire->estActif();
    }
}
