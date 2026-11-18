<?php

namespace App\DTOs\Member;

use App\Enums\VoicePart;

class ListMembersDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly ?VoicePart $voice,
        public readonly int $page,
    ) {}
}
