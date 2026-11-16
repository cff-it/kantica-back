<?php

namespace App\Policies;

use App\Models\Mass;
use App\Models\User;

class MassPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Mass $mass): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    public function update(User $user, Mass $mass): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    public function delete(User $user, Mass $mass): bool
    {
        return $user->hasRole('admin');
    }
}
