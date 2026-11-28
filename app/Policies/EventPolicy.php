<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        return true;
    }

    // Création d'un événement (KAN-12, formulaire « Nouvel événement ») : Secrétaire et Admin, à confirmer avec la
    // présidente. Appelée avec la classe, sans événement, pour que le refus 403 passe avant tout 404. Le rôle
    // « responsable » n'existe pas dans RoleName : il ne donnait accès à personne
    public function create(User $user): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value,]);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->hasRole('admin');
    }

    public function manageRegistrations(User $user, Event $event): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    // Saisie des présences (KAN-12, US-11) : Secrétaire et Admin, depuis le Profil. Appelée avec la classe, sans
    // événement, pour que le refus 403 passe avant tout 404
    public function manageAttendances(User $user): bool
    {
        return $user->hasRole([RoleName::Admin->value, RoleName::Secretary->value,]);
    }
}
