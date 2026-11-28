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

    // Même règle que la liste : la route `GET /songs/{song}` l'applique avec `viewAny`, sans charger le cantique
    public function view(User $user, Song $song): bool
    {
        return $this->viewAny($user);
    }

    // Ajout et modification réservés à l'admin (bouton « + Ajouter », KAN-13). Le rôle « responsable », cité ici
    // auparavant, n'existe pas (RoleName) : la règle effective reste la même
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    // Appelée avec la classe par la route (sans cantique, avant tout 404) et avec le cantique par la FormRequest de
    // KAN-38 : le cantique est donc facultatif
    public function update(User $user, ?Song $song = null): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    // Appelée avec la classe par la route, avant tout 404
    public function delete(User $user, ?Song $song = null): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
