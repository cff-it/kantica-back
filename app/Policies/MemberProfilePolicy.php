<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Fiches de recensement des autres membres (US-08), enregistrée pour le modèle UserProfile.
 *
 * Aucune règle par fiche : seul le rôle compte, les abilities sont donc appelées avec la classe
 * (`->can('view', UserProfile::class)`). Un membre consulte et modifie sa propre fiche via `/api/me/profile`.
 */
class MemberProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value,]);
    }
}
