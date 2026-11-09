<?php

namespace App\Repositories;

use App\Models\Firstname;
use App\Models\User;
use App\Models\UserProfile;

class MemberProfileRepository
{
    public function load(User $user): User
    {
        return $user->load(['profile.voiceGroup', 'profile.voice', 'firstnames',]);
    }

    public function updateLastName(User $user, string $lastName): void
    {
        $user->update(['last_name' => $lastName,]);
    }

    /**
     * Remplace tous les prénoms de l'utilisateur : un mot = une ligne, dans l'ordre de saisie.
     * Le premier mot est le prénom par défaut.
     *
     * @param  list<string>  $firstNames
     */
    public function replaceFirstnames(User $user, array $firstNames): void
    {
        Firstname::where('user_id', $user->id)->delete();

        foreach ($firstNames as $order => $firstName) {
            Firstname::create([
                'user_id'    => $user->id,
                'first_name' => $firstName,
                'order'      => $order,
                'default'    => $order === 0,
            ]);
        }
    }

    /**
     * @param  array{voice_group_id: int, voice_id: int|null, phone: string}  $attributes
     */
    public function upsertProfile(User $user, array $attributes): void
    {
        UserProfile::updateOrCreate(['user_id' => $user->id,], $attributes);
    }
}
