<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Song;
use App\Models\User;

class SongPolicy
{
    // Répertoire (US-04) : tous les rôles, de Membre à Admin. Le compte doit aussi être validé (middleware de la route)
    public function viewAny(User $user): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value, RoleName::Member->value,]);
    }

    public function view(User $user, Song $song): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    public function update(User $user, Song $song): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    public function delete(User $user, Song $song): bool
    {
        return $user->hasRole('admin');
    }
}
