<?php

namespace App\DTOs\Song;

use App\Models\Song;
use Illuminate\Pagination\LengthAwarePaginator;

class SongListResultDTO
{
    /**
     * @param  LengthAwarePaginator<int, Song>  $songs  page courante, après recherche
     * @param  int  $songsCount  nombre de cantiques du répertoire (« 13 cantiques »), hors recherche
     */
    public function __construct(
        public readonly LengthAwarePaginator $songs,
        public readonly int $songsCount,
    ) {}
}
