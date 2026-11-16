<?php

namespace App\DTOs\Mass;

use Carbon\Carbon;

class CreateMassDTO
{
    public function __construct(
        public readonly Carbon $date,
        public readonly ?string $liturgicalName,
        public readonly ?string $prayerCycle,
        public readonly ?string $notes,
        public readonly bool $isPublic = true,
    ) {}
}
