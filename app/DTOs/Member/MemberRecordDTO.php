<?php

namespace App\DTOs\Member;

use App\Support\PartialDate;

// Fiche de recensement complète d'un membre, telle que la secrétaire la modifie (écran « Modifier la fiche »)
class MemberRecordDTO
{
    public function __construct(
        public readonly string $lastName,
        public readonly string $firstName,
        public readonly string $voiceGroup,
        // Voix précise (S1, A2...) ; `voiceProvided` à false : la clé est absente du payload, la voix reste inchangée
        public readonly ?string $voice,
        public readonly bool $voiceProvided,
        public readonly string $phone,
        public readonly PartialDate $birthDate,
        public readonly string $birthPlace,
        public readonly string $address,
        public readonly ?string $socialPseudo,
        public readonly ?string $registrationNumber,
        public readonly PartialDate $adhesionDate,
        public readonly ?PartialDate $engagementDate,
        public readonly ?string $engagementPlace,
        public readonly MemberSacramentDTO $baptism,
        public readonly MemberSacramentDTO $firstCommunion,
        public readonly MemberSacramentDTO $confirmation,
        public readonly ?MemberMarriageDTO $marriage,
        public readonly MemberEmergencyContactDTO $emergencyContact,
    ) {}
}
