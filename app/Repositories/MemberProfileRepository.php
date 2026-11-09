<?php

namespace App\Repositories;

use App\Enums\SacramentType;
use App\Models\Firstname;
use App\Models\User;
use App\Models\UserEmergencyContact;
use App\Models\UserEngagement;
use App\Models\UserProfile;
use App\Models\UserSacrament;

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
     * @param  array<string, mixed>  $attributes  colonnes de `user_profiles`
     */
    public function upsertProfile(User $user, array $attributes): void
    {
        UserProfile::updateOrCreate(['user_id' => $user->id,], $attributes);
    }

    /**
     * @param  array{adhesion_date: mixed, engagement_date: mixed, engagement_place: string|null}  $attributes
     */
    public function upsertEngagement(User $user, array $attributes): void
    {
        UserEngagement::updateOrCreate(['user_id' => $user->id,], $attributes);
    }

    // `user_sacraments` : une ligne par sacrement (baptême, première communion, confirmation, mariage)
    /**
     * @param  array{date: mixed, place: string|null}  $attributes
     */
    public function upsertSacrament(User $user, SacramentType $type, array $attributes): void
    {
        UserSacrament::updateOrCreate(['user_id' => $user->id, 'type' => $type,], $attributes);
    }

    // Le mariage garde en plus le conjoint
    /**
     * @param  array{spouse_name: string|null, spouse_is_member: bool, date: mixed, place: string|null}  $attributes
     */
    public function upsertMarriage(User $user, array $attributes): void
    {
        UserSacrament::updateOrCreate(['user_id' => $user->id, 'type' => SacramentType::Marriage,], $attributes);
    }

    public function updatePicture(int $userId, string $url): void
    {
        UserProfile::where('user_id', $userId)->update(['picture_url' => $url,]);
    }

    public function addEmergencyContact(User $user, string $name, ?string $phone, ?string $relationship): void
    {
        UserEmergencyContact::create([
            'user_id'       => $user->id,
            'contact_name'  => $name,
            'contact_phone' => $phone,
            'relationship'  => $relationship,
        ]);
    }
}
