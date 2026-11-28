<?php

namespace App\Actions\Song;

use App\Repositories\SongRepository;

// Suppression d'un cantique, réservée à l'admin (SongPolicy::delete, contrôlé sur la route avant tout 404). Le cantique
// n'est lu qu'une fois : 404 s'il n'existe pas
readonly class DeleteSongAction
{
    public function __construct(private SongRepository $songs) {}

    public function __invoke(int $songId): void
    {
        $this->songs->delete($this->songs->findById($songId));
    }
}
