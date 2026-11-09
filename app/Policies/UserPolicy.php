<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\UserStatus;
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

    // Voix de sa propre fiche : libre tant que le compte est en attente, ensuite réservée aux admins.
    // Un compte validé sans fiche peut la saisir une première fois (sinon sa fiche ne pourrait jamais être enregistrée).
    public function updateOwnVoice(User $user): bool
    {
        return $user->status !== UserStatus::Active
            || $user->profile === null
            || $user->hasRole(RoleName::Admin->value);
    }
}
