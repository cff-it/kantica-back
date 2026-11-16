<?php

namespace App\DTOs\Mass;

class AssignSongToMassPartDTO
{
    public function __construct(
        public readonly int $massPartId,
        public readonly int $songId,
        public readonly ?string $specificTitle = null,
    ) {}
}
