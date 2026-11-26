<?php

namespace App\Actions\Song;

use App\DTOs\Song\UpdateSongDTO;
use App\Models\Song;
use App\Repositories\SongRepository;
use Illuminate\Support\Facades\DB;

readonly class UpdateSongAction
{
    public function __construct(
        private SongRepository $songs,
        private SyncSongSectionsAction $syncSections,
    ) {}

    // Le cantique et ses sections sont enregistrés ensemble ou pas du tout. Sans liste de sections dans la requête,
    // les sections existantes sont conservées ; une liste vide les supprime toutes.
    public function __invoke(UpdateSongDTO $dto): Song
    {
        return DB::transaction(function () use ($dto): Song {
            $song = $this->songs->update($this->songs->findById($dto->id), [
                'title'     => $dto->title,
                'author'    => $dto->author,
                'reference' => $dto->reference,
                'notes'     => $dto->notes,
            ]);

            if ($dto->sections !== null) {
                ($this->syncSections)($song, $dto->sections);
            }

            return $song->load('sections');
        });
    }
}
