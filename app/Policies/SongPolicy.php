<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

// Aucun droit du répertoire ne dépend du cantique : les routes appellent la Policy avec la classe (403 avant tout 404).
// Un appel avec le cantique, comme `can('update', $song)` dans les FormRequests de KAN-38, reste accepté : PHP ignore
// l'argument en plus
class SongPolicy
{
    // Répertoire (US-04) : tous les rôles, de Membre à Admin. Le compte doit aussi être validé (middleware de la route)
    public function viewAny(User $user): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value, RoleName::Member->value,]);
    }

    // Un cantique se lit avec le même droit que la liste. La route `GET /songs/{song}` applique `viewAny` ; `view` reste
    // l'entrée standard d'un `can('view', $song)` (fiche, KAN-25) et ne peut pas s'en écarter
    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    // Ajout, modification et suppression réservés à l'admin (KAN-13). Le rôle « responsable », cité ici auparavant,
    // n'existe pas (RoleName) : la règle effective reste la même
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function delete(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
