<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Enums\VoicePart;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    public function all(): Collection
    {
        return User::with('firstnames')->orderBy('last_name')->get();
    }

    // Membres actifs triés par nom, éventuellement d'une seule voix. Ne font pas partie des membres : les comptes en
    // attente et le compte administrateur originel (compte technique, identifié par SUPER_ADMIN_MAIL).
    // Requête commune à l'export et à la liste de la secrétaire (US-08).
    public function members(?VoicePart $voice = null): Collection
    {
        $superAdminMail = config('kantica.super_admin.mail');

        return User::with(['firstnames', 'profile.voiceGroup', 'profile.voice',])
            ->where('status', UserStatus::Active->value)
            ->when(filled($superAdminMail), fn ($query) => $query->where('mail', '!=', $superAdminMail))
            ->when($voice, fn ($query) => $query->whereHas(
                'profile.voiceGroup',
                fn ($groups) => $groups->where('abbreviation', $voice->value),
            ))
            ->orderBy('last_name')
            ->orderBy('id')
            ->get();
    }

    public function findById(int $id): User
    {
        return User::with(['firstnames', 'profile.voiceGroup', 'profile.voice',])->findOrFail($id);
    }

    public function findByMail(string $mail): ?User
    {
        return User::where('mail', $mail)->first();
    }

    public function existsWithRole(string $role): bool
    {
        return User::role($role)->exists();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    // Données nécessaires au front pour router l'utilisateur courant et afficher sa fiche
    public function loadCurrentUser(User $user): User
    {
        return $user->loadMissing([
            'roles',
            'firstnames',
            'profile.voiceGroup',
            'profile.voice',
        ]);
    }

    // Retourne true uniquement si le compte vient de passer de « en attente » à « actif »
    public function activate(User $user): bool
    {
        $updated = User::whereKey($user->id)
            ->where('status', UserStatus::Pending->value)
            ->update(['status' => UserStatus::Active->value,]);

        return $updated === 1;
    }
}
