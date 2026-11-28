<?php

namespace App\DTOs\Song;

class UpdateSongDTO
{
    /**
     * @param  list<SongSectionDTO>|null  $sections  liste complète et ordonnée des sections ; null : sections inchangées
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $author,
        public readonly ?string $reference,
        public readonly ?string $notes,
        public readonly ?array $sections,
    ) {}
}
