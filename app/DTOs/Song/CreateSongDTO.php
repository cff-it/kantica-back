<?php

namespace App\DTOs\Song;

class CreateSongDTO
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $author,
        public readonly ?string $reference,
        public readonly ?string $lyrics,
        public readonly ?string $notes,
    ) {}
}
