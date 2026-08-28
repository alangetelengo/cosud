<?php

namespace App\Policies;

use App\Models\FournisseurPrestataire;
use App\Models\User;

class FournisseurPrestatairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fournisseurs-prestataires.view');
    }

    public function view(User $user, FournisseurPrestataire $fournisseurPrestataire): bool
    {
        return $user->can('fournisseurs-prestataires.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fournisseurs-prestataires.create');
    }

    public function update(User $user, FournisseurPrestataire $fournisseurPrestataire): bool
    {
        return $user->can('fournisseurs-prestataires.create');
    }

    public function delete(User $user, FournisseurPrestataire $fournisseurPrestataire): bool
    {
        return $user->can('fournisseurs-prestataires.create');
    }
}
