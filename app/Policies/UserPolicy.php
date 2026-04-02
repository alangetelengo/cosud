<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('utilisateurs.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('utilisateurs.view');
    }

    public function create(User $user): bool
    {
        return $user->can('utilisateurs.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('utilisateurs.edit');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('utilisateurs.delete') && $model->id !== $user->id;
    }
}
