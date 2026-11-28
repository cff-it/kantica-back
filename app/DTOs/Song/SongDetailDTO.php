<?php

namespace App\DTOs\Song;

use App\Models\Song;

// Le cantique avec ses sections (chargées) et son historique d'usage : ce que renvoie GET /api/songs/{song}
readonly class SongDetailDTO
{
    public function __construct(
        public Song $song,
        public SongUsageDTO $usage,
    ) {}
}
