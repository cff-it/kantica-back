<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole('admin');
    }

    public function assignRole(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }

    public function validate(User $user, User $member): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value,]);
    }
}
