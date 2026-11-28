<?php

namespace App\DTOs\Event;

class RecordAttendancesDTO
{
    /**
     * @param  list<int>  $presentMemberIds  liste complète des membres présents (les autres sont absents)
     */
    public function __construct(
        public readonly int $eventId,
        public readonly array $presentMemberIds,
    ) {}
}
