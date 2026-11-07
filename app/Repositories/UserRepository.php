<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    public function all(): Collection
    {
        return User::with('firstnames')->orderBy('last_name')->get();
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
