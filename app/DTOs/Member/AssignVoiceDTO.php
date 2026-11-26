<?php

namespace App\DTOs\Member;

use App\Enums\VoicePart;

class AssignVoiceDTO
{
    public function __construct(
        public readonly int $memberId,
        public readonly VoicePart $voice,
    ) {}
}
