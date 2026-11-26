<?php

namespace App\Actions\Song;

use App\DTOs\Song\CreateSongDTO;
use App\Models\Song;
use App\Repositories\SongRepository;
use Illuminate\Support\Facades\DB;

readonly class CreateSongAction
{
    public function __construct(
        private SongRepository $songs,
        private SyncSongSectionsAction $syncSections,
    ) {}

    // Le cantique et ses sections sont enregistrés ensemble ou pas du tout
    public function __invoke(CreateSongDTO $dto): Song
    {
        return DB::transaction(function () use ($dto): Song {
            $song = $this->songs->create([
                'title'     => $dto->title,
                'author'    => $dto->author,
                'reference' => $dto->reference,
                'notes'     => $dto->notes,
            ]);

            if ($dto->sections !== []) {
                ($this->syncSections)($song, $dto->sections);
            }

            return $song->load('sections');
        });
    }
}
