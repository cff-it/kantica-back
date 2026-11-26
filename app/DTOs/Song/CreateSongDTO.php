<?php

namespace App\DTOs\Song;

class CreateSongDTO
{
    /**
     * @param  list<SongSectionDTO>  $sections  sections dans l'ordre de saisie (vide : cantique sans section)
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $author,
        public readonly ?string $reference,
        public readonly ?string $notes,
        public readonly array $sections,
    ) {}
}
