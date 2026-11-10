<?php

namespace App\DTOs\Song;

class UpdateSongDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $author,
        public readonly ?string $reference,
        public readonly ?string $lyrics,
        public readonly ?string $notes,
    ) {}
}
