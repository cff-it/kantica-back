<?php

namespace App\DTOs\Event;

use Carbon\Carbon;

class CheckInDTO
{
    public function __construct(
        public readonly int $eventId,
        public readonly int $userId,
        public readonly Carbon $dateIn,
    ) {}
}
