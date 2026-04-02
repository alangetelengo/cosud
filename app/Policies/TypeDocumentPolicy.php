<?php

namespace App\Policies;

use App\Models\TypeDocument;
use App\Models\User;

class TypeDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('types-documents.view');
    }

    public function view(User $user, TypeDocument $typeDocument): bool
    {
        return $user->can('types-documents.view');
    }

    public function create(User $user): bool
    {
        return $user->can('types-documents.create');
    }

    public function update(User $user, TypeDocument $typeDocument): bool
    {
        return $user->can('types-documents.edit');
    }

    public function delete(User $user, TypeDocument $typeDocument): bool
    {
        return $user->can('types-documents.delete');
    }
}
