<?php

namespace App\DTOs\Member;

use App\Support\PartialDate;

// Mariage de la fiche (facultatif) : au moins un des trois champs est renseigné
class MemberMarriageDTO
{
    public function __construct(
        public readonly ?string $spouse,
        public readonly ?PartialDate $date,
        public readonly ?string $place,
    ) {}
}
