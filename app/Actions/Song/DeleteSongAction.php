<?php

namespace App\Actions\Song;

use App\Repositories\SongRepository;

// Suppression d'un cantique. Le droit (admin seulement, SongPolicy::delete) est contrôlé par la route, avant tout 404 :
// l'Action ne le vérifie pas, un appel depuis une commande ou un job ne serait donc pas contrôlé. Le cantique n'est lu
// qu'une fois (404 s'il n'existe pas) et supprimé par le modèle, pour que les événements Eloquent suivent
readonly class DeleteSongAction
{
    public function __construct(private SongRepository $songs) {}

    public function __invoke(int $songId): void
    {
        $this->songs->delete($this->songs->findById($songId));
    }
}
