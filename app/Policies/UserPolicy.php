<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    // « Gestion des rôles » (US-25) et routes /api/users : réservées à l'admin
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole(RoleName::Admin->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole(RoleName::Admin->value);
    }

    // Seul l'admin change le rôle d'un compte (US-25) ; appelée avec la classe, sans compte ciblé, pour que le refus
    // 403 passe avant tout 404
    public function assignRole(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function validate(User $user, User $member): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value,]);
    }

    // Export de la liste des membres (PDF / CSV / Excel)
    public function export(User $user): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value,]);
    }

    // Import de membres depuis un fichier Excel (KAN-31)
    public function import(User $user): bool
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
