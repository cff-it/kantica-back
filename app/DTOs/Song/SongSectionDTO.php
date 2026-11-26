<?php

namespace App\DTOs\Song;

use App\Enums\SectionType;

// Une section (couplet, refrain ou pont) reçue dans le payload d'un cantique. Sans identifiant, la section est nouvelle.
// Son numéro (Couplet 1, 2, 3) est saisi, facultatif. Sa position est celle de son rang dans la liste reçue : elle n'est
// pas portée par le DTO.
class SongSectionDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly SectionType $type,
        public readonly ?int $number,
        public readonly string $text,
    ) {}
}
