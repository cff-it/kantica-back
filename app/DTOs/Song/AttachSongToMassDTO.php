<?php

namespace App\DTOs\Song;

class AttachSongToMassDTO
{
    public function __construct(
        public readonly int $songId,
        public readonly int $massPartId,
        public readonly int $order,
    ) {}
}
