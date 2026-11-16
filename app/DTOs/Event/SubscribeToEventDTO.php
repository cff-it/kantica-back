<?php

namespace App\DTOs\Event;

use Carbon\Carbon;

class SubscribeToEventDTO
{
    public function __construct(
        public readonly int $eventId,
        public readonly int $userId,
        public readonly Carbon $subscribedAt,
    ) {}
}
