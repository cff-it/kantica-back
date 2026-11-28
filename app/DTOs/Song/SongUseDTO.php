<?php

namespace App\DTOs\Song;

// Une fois où un cantique a été chanté : la messe, sa date (`2026-11-15`), son nom liturgique (null s'il n'est pas
// renseigné) et la partie de la messe (Entrée, Communion...)
readonly class SongUseDTO
{
    public function __construct(
        public int $massId,
        public string $date,
        public ?string $occasion,
        public string $part,
    ) {}
}
