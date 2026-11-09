<?php

namespace App\DTOs\Member;

class MemberProfileDTO
{
    public function __construct(
        public readonly string $lastName,
        public readonly string $firstName,
        public readonly ?string $voiceGroup,
        public readonly ?string $voice,
        public readonly string $phone,
    ) {}
}
