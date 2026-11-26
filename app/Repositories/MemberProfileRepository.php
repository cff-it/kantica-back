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

    // Le prénom saisi est le prénom par défaut (les autres prénoms restent intacts). Utilisé par l'import des membres
    // (KAN-31), qui garde le prénom d'un compte importé en une seule ligne ; les fiches utilisent replaceFirstnames()
    public function upsertDefaultFirstname(User $user, string $firstName): void
    {
        Firstname::updateOrCreate(
            ['user_id' => $user->id, 'default' => true,],
            ['first_name' => $firstName, 'order' => 0,],
        );
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

    public function existsFor(User $user): bool
    {
        return UserProfile::where('user_id', $user->id)->exists();
    }

    // Change le groupe de voix d'une fiche et efface sa voix précise (A2...), qui n'appartient qu'à l'ancien groupe.
    // Une seule requête UPDATE conditionnelle, sans lecture préalable : rien n'est écrit si le groupe est déjà le bon,
    // et deux appels concurrents (ou un PUT complet en parallèle) ne peuvent pas réécrire une voix précise périmée.
    // Renvoie le nombre de fiches modifiées (0 ou 1). Les autres champs de la fiche ne sont pas touchés.
    public function changeVoiceGroup(User $user, int $voiceGroupId): int
    {
        return UserProfile::where('user_id', $user->id)
            ->where('voice_group_id', '<>', $voiceGroupId)
            ->update([
                'voice_group_id' => $voiceGroupId,
                'voice_id'       => null,
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  colonnes de `user_engagements`, précisions de date comprises
     *                                            (`adhesion_date_precision`, `engagement_date_precision`)
     */
    public function upsertEngagement(User $user, array $attributes): void
    {
        UserEngagement::updateOrCreate(['user_id' => $user->id,], $attributes);
    }

    // `user_sacraments` : une ligne par sacrement (baptême, première communion, confirmation, mariage)
    /**
     * @param  array<string, mixed>  $attributes  `date`, `place` et `date_precision` (day par défaut en base)
     */
    public function upsertSacrament(User $user, SacramentType $type, array $attributes): void
    {
        UserSacrament::updateOrCreate(['user_id' => $user->id, 'type' => $type,], $attributes);
    }

    // Le mariage garde en plus le conjoint
    /**
     * @param  array<string, mixed>  $attributes  `spouse_name`, `date`, `date_precision` et `place` ; `spouse_is_member`
     *                                            reste inchangé quand il est absent
     */
    public function upsertMarriage(User $user, array $attributes): void
    {
        UserSacrament::updateOrCreate(['user_id' => $user->id, 'type' => SacramentType::Marriage,], $attributes);
    }

    public function deleteMarriage(User $user): void
    {
        UserSacrament::where('user_id', $user->id)->where('type', SacramentType::Marriage)->delete();
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

    /**
     * La fiche n'a qu'un bloc « contact en cas d'urgence » : la première ligne est mise à jour (son UUID est conservé),
     * les éventuelles autres sont supprimées.
     */
    public function replaceEmergencyContact(User $user, string $name, ?string $phone, ?string $relationship): void
    {
        $contact = UserEmergencyContact::where('user_id', $user->id)->orderBy('id')->first();

        if ($contact === null) {
            $this->addEmergencyContact($user, $name, $phone, $relationship);

            return;
        }

        $contact->update(['contact_name' => $name, 'contact_phone' => $phone, 'relationship' => $relationship,]);
        UserEmergencyContact::where('user_id', $user->id)->where('id', '!=', $contact->id)->delete();
    }
}
