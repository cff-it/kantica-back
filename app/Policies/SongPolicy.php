<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Song;
use App\Models\User;

// Aucun droit du répertoire ne dépend encore du cantique, d'où `?Song $song = null` : les routes appellent la Policy avec
// la classe (403 avant tout 404), les FormRequests de KAN-38 et la future fiche (KAN-25) avec le cantique. SongPolicyTest
// vérifie que les deux appels donnent la même réponse
class SongPolicy
{
    // Répertoire (US-04) : tous les rôles, de Membre à Admin. Le compte doit aussi être validé (middleware de la route)
    public function viewAny(User $user): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value, RoleName::Member->value,]);
    }

    // Un cantique se lit avec le même droit que la liste (la route `GET /songs/{song}` applique `viewAny`)
    public function view(User $user, ?Song $song = null): bool
    {
        return $this->viewAny($user);
    }

    // Ajout, modification et suppression réservés à l'admin (KAN-13). Le rôle « responsable », cité ici auparavant,
    // n'existe pas (RoleName) : la règle effective reste la même
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user, ?Song $song = null): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function delete(User $user, ?Song $song = null): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
