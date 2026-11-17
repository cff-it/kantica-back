<?php

namespace App\DTOs\Member;

use App\Support\PartialDate;

// Baptême, première communion ou confirmation de la fiche : une date (au jour, au mois ou à l'année) et un lieu
class MemberSacramentDTO
{
    public function __construct(
        public readonly PartialDate $date,
        public readonly string $place,
    ) {}
}
