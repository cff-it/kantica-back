<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Models\UserImport;

class UserImportPolicy
{
    // Le suivi d'un import (et son rapport, avec les noms des membres) est réservé à celui qui l'a lancé et à l'admin
    public function view(User $user, UserImport $import): bool
    {
        return $user->id === $import->user_id || $user->hasRole(RoleName::Admin->value);
    }
}
