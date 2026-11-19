<?php

namespace App\Actions\Member;

use App\DTOs\Member\MemberRecordDTO;
use App\Enums\DatePrecision;
use App\Enums\SacramentType;
use App\Models\User;
use App\Repositories\MemberProfileRepository;

// Écrit la fiche de recensement complète d'un membre (nom, prénoms, profil, engagement, sacrements, mariage et contact
// d'urgence), qu'elle existe déjà (UpdateMemberProfileAction) ou non (CreateMemberAction). Plusieurs tables sont écrites :
// à appeler dans une transaction. La voix est déjà résolue par l'appelant, qui connaît la règle à appliquer.
readonly class SaveMemberRecordAction
{
    public function __construct(
        private MemberProfileRepository $profiles,
    ) {}

    public function __invoke(User $member, MemberRecordDTO $dto, int $voiceGroupId, ?int $voiceId): void
    {
        $this->profiles->updateLastName($member, $dto->lastName);
        $this->profiles->replaceFirstnames($member, $this->splitFirstName($dto->firstName));
        $this->profiles->upsertProfile($member, [
            'voice_group_id'             => $voiceGroupId,
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
