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

    // Le prénom saisi sur la fiche est le prénom par défaut (les autres prénoms restent intacts)
    public function upsertDefaultFirstname(User $user, string $firstName): void
    {
        Firstname::updateOrCreate(
            ['user_id' => $user->id, 'default' => true,],
            ['first_name' => $firstName, 'order' => 0,],
        );
    }

    /**
     * @param  array{voice_group_id: int, voice_id: int|null, phone: string}  $attributes
     */
    public function upsertProfile(User $user, array $attributes): void
    {
        UserProfile::updateOrCreate(['user_id' => $user->id,], $attributes);
    }
}
