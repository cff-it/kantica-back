<?php

namespace App\DTOs\Member;

use App\Enums\ExportFormat;
use App\Enums\VoicePart;

class ExportMembersDTO
{
    public function __construct(
        public readonly ExportFormat $format,
        public readonly ?VoicePart $voice,
    ) {}
}
