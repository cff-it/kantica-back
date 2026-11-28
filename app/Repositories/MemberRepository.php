<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\MemberSortKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MemberRepository
{
    // Tous les membres recensés pour la liste de la secrétaire (US-08), comptes en attente compris.
    public function allRegistered(): Collection
    {
        return $this->registered()
            ->with(['firstnames', 'profile.voiceGroup', 'profile.voice',])
            ->get();
    }

    // Membres dont la présence se saisit (US-11), triés par nom puis prénom : la liste de la saisie des présences
    public function allAttendees(): Collection
    {
        return $this->attendees()
            ->with(['firstnames', 'profile.voiceGroup', 'profile.voice',])
            ->get()
            ->sortBy(fn (User $member) => MemberSortKey::of($member))
            ->values();
    }

    /**
     * Identifiants des membres dont la présence peut être saisie.
     *
     * @return list<int>
     */
    public function attendeeIds(): array
    {
        return $this->attendees()->pluck('id')->all();
    }

    // Les mêmes identifiants en sous-requête : l'aperçu des événements ne compte que les présences de la liste de saisie
    public function attendeeIdsQuery(): Builder
    {
        return $this->attendees()->select('id');
    }

    // Effectif de la liste de saisie (« 8 / 9 présents »)
    public function countAttendees(): int
    {
        return $this->attendees()->count();
    }

    // Membres actifs et comptes en attente (la maquette de la saisie en montre un : réponse du ticket KAN-12)
    private function attendees(): Builder
    {
        return $this->registered()->whereIn('status', [UserStatus::Active->value, UserStatus::Pending->value,]);
    }

    // Seul le compte administrateur originel (compte technique, identifié par SUPER_ADMIN_MAIL) n'est pas un choriste.
    private function registered(): Builder
    {
        $superAdminMail = config('kantica.super_admin.mail');

        return User::query()
            ->when(filled($superAdminMail), fn (Builder $query) => $query->where('mail', '!=', $superAdminMail));
    }
}
