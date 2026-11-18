<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class MemberRepository
{
    // Tous les membres recensés pour la liste de la secrétaire (US-08), comptes en attente compris.
    // Seul le compte administrateur originel (compte technique, identifié par SUPER_ADMIN_MAIL) n'est pas un choriste.
    public function allRegistered(): Collection
    {
        $superAdminMail = config('kantica.super_admin.mail');

        return User::with(['firstnames', 'profile.voiceGroup', 'profile.voice',])
            ->when(filled($superAdminMail), fn ($query) => $query->where('mail', '!=', $superAdminMail))
            ->get();
    }
}
