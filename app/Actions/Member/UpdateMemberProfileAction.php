<?php

namespace App\Actions\Member;

use App\DTOs\Member\MemberRecordDTO;
use App\Enums\DatePrecision;
use App\Enums\SacramentType;
use App\Models\User;
use App\Repositories\MemberProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Support\Facades\DB;

readonly class UpdateMemberProfileAction
{
    public function __construct(
        private UserRepository $users,
        private MemberProfileRepository $profiles,
        private VoiceRepository $voices,
    ) {}

    // Met à jour la fiche de recensement complète d'un autre membre (créée si elle n'existe pas encore). Aucune règle de
    // voix : la secrétaire et l'admin peuvent la changer. L'email, le statut et la photo ne sont jamais modifiés ici.
    public function __invoke(int $memberId, MemberRecordDTO $dto): User
    {
        $member = $this->users->findRecordById($memberId);
        $voiceGroup = $this->voices->findGroupByAbbreviation($dto->voiceGroup);
        $voice = $dto->voice !== null ? $this->voices->findVoiceInGroup($voiceGroup, $dto->voice) : null;

        // Sans clé `voice`, la voix précise reste, sauf si le groupe change : l'ancienne voix n'en ferait plus partie
        $keepVoice = !$dto->voiceProvided && $member->profile?->voice_group_id === $voiceGroup->id;
        $voiceId = $keepVoice ? $member->profile->voice_id : $voice?->id;

        DB::transaction(function () use ($member, $dto, $voiceGroup, $voiceId): void {
            $this->profiles->updateLastName($member, $dto->lastName);
            $this->profiles->replaceFirstnames($member, $this->splitFirstName($dto->firstName));
            $this->profiles->upsertProfile($member, [
                'voice_group_id'             => $voiceGroup->id,
                'voice_id'                   => $voiceId,
                'phone'                      => $dto->phone,
                'birth_date'                 => $dto->birthDate->date,
                'birth_place'                => $dto->birthPlace,
                'address'                    => $dto->address,
                'social_pseudo'              => $dto->socialPseudo,
                'region_registration_number' => $dto->registrationNumber,
            ]);
            $this->saveEngagement($member, $dto);
            $this->saveSacraments($member, $dto);
            $this->saveMarriage($member, $dto);
            $this->profiles->replaceEmergencyContact(
                $member,
                $dto->emergencyContact->name,
                $dto->emergencyContact->phone,
                implode(', ', $dto->emergencyContact->relationships),
            );
        });

        // Rechargé depuis la base : les relations déjà chargées sur $member sont périmées
        return $this->users->findRecordById($memberId);
    }

    private function saveEngagement(User $member, MemberRecordDTO $dto): void
    {
        $this->profiles->upsertEngagement($member, [
            'adhesion_date'             => $dto->adhesionDate->date,
            'adhesion_date_precision'   => $dto->adhesionDate->precision,
            'engagement_date'           => $dto->engagementDate?->date,
            'engagement_date_precision' => $dto->engagementDate?->precision ?? DatePrecision::Day,
            'engagement_place'          => $dto->engagementPlace,
        ]);
    }

    private function saveSacraments(User $member, MemberRecordDTO $dto): void
    {
        $sacraments = [
            [SacramentType::Baptism, $dto->baptism,],
            [SacramentType::FirstCommunion, $dto->firstCommunion,],
            [SacramentType::Confirmation, $dto->confirmation,],
        ];

        foreach ($sacraments as [$type, $sacrament]) {
            $this->profiles->upsertSacrament($member, $type, [
                'date'           => $sacrament->date->date,
                'date_precision' => $sacrament->date->precision,
                'place'          => $sacrament->place,
            ]);
        }
    }

    // Sans conjoint, date ni lieu, il n'y a pas de mariage. `spouse_is_member` n'est pas sur la maquette : inchangé
    private function saveMarriage(User $member, MemberRecordDTO $dto): void
    {
        if ($dto->marriage === null) {
            $this->profiles->deleteMarriage($member);

            return;
        }

        $this->profiles->upsertMarriage($member, [
            'spouse_name'    => $dto->marriage->spouse,
            'date'           => $dto->marriage->date?->date,
            'date_precision' => $dto->marriage->date?->precision ?? DatePrecision::Day,
            'place'          => $dto->marriage->place,
        ]);
    }

    /**
     * Chaque mot du prénom saisi est un prénom à part entière ("Hanta Rasoa" donne deux lignes), comme pour la fiche
     * personnelle (UpdateOwnProfileAction). Les espaces en trop ne créent pas de prénom vide.
     *
     * @return list<string>
     */
    private function splitFirstName(string $firstName): array
    {
        return array_values(array_filter(explode(' ', $firstName), fn (string $word) => $word !== ''));
    }
}
