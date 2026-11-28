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

    // Membres actifs, triés par nom puis prénom : la liste de la saisie des présences (US-11)
    public function allActive(): Collection
    {
        return $this->registered()
            ->where('status', UserStatus::Active->value)
            ->with(['firstnames', 'profile.voiceGroup', 'profile.voice',])
            ->get()
            ->sortBy(fn (User $member) => MemberSortKey::of($member))
            ->values();
    }

    /**
     * Identifiants des membres actifs : les seuls dont la présence peut être saisie.
     *
     * @return list<int>
     */
    public function activeIds(): array
    {
        return $this->registered()
            ->where('status', UserStatus::Active->value)
            ->pluck('id')
            ->all();
    }

    // Seul le compte administrateur originel (compte technique, identifié par SUPER_ADMIN_MAIL) n'est pas un choriste.
    private function registered(): Builder
    {
        $superAdminMail = config('kantica.super_admin.mail');

        return User::query()
            ->when(filled($superAdminMail), fn (Builder $query) => $query->where('mail', '!=', $superAdminMail));
    }
}
