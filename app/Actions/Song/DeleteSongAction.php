<?php

namespace App\Actions\Song;

use App\Models\Song;
use App\Repositories\SongRepository;

// Suppression d'un cantique, réservée à l'admin (DeleteSongRequest). Le cantique arrive déjà chargé par la requête,
// qui en a besoin pour la Policy : il n'est pas relu ici
readonly class DeleteSongAction
{
    public function __construct(private SongRepository $songs) {}

    public function __invoke(Song $song): void
    {
        $this->songs->delete($song);
    }
}
