<?php

namespace App\DTOs\Song;

// Où et quand un cantique a été chanté. `timesSung` compte toutes les utilisations ; `history` ne garde que les plus
// récentes (voir ShowSongAction), de la plus récente à la plus ancienne, et `lastSung` en est le premier élément.
readonly class SongUsageDTO
{
    /**
     * @param  list<SongUseDTO>  $history
     */
    public function __construct(
        public int $timesSung,
        public ?SongUseDTO $lastSung,
        public array $history,
    ) {}
}
