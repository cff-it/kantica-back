<?php

namespace Tests\Feature\Member\Concerns;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Firstname;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\VoiceGroup;

trait CreatesMembers
{
    /**
     * Membre avec ses prénoms (un mot = une ligne, le premier est le prénom par défaut) et, si un groupe de voix
     * est donné, sa fiche de recensement. Les rôles et les groupes de voix doivent être seedés.
     */
    protected function member(
        string $lastName,
        string $firstName,
        ?string $voiceGroup = 'S',
        UserStatus $status = UserStatus::Active,
        RoleName $role = RoleName::Member,
    ): User {
        $member = User::factory()->create(['last_name' => $lastName, 'status' => $status,]);
        $member->assignRole($role->value);

        foreach (explode(' ', $firstName) as $order => $word) {
            Firstname::create([
                'user_id'    => $member->id,
                'first_name' => $word,
                'order'      => $order,
                'default'    => $order === 0,
            ]);
        }

        if ($voiceGroup !== null) {
            UserProfile::create([
                'user_id'        => $member->id,
                'voice_group_id' => VoiceGroup::where('abbreviation', $voiceGroup)->value('id'),
                'phone'          => '034 12 345 67',
            ]);
        }

        return $member;
    }

    protected function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
