<?php

namespace App\DTOs\Event;

use App\Enums\EventType;

class ListAttendanceOverviewDTO
{
    public function __construct(
        public readonly ?EventType $type,
        public readonly int $page,
    ) {}
}
