<?php

namespace App\Actions\Song;

use App\DTOs\Song\AttachSongToMassDTO;
use App\Models\MassPart;
use App\Repositories\MassRepository;
use App\Repositories\SongRepository;

readonly class AttachSongToMassAction
{
    public function __construct(
        private SongRepository $songs,
        private MassRepository $masses,
    ) {}

    public function __invoke(AttachSongToMassDTO $dto): MassPart
    {
        $song     = $this->songs->findById($dto->songId);
        $massPart = $this->masses->findPartById($dto->massPartId);

        $massPart->massSongs()->create([
            'song_id' => $song->id,
        ]);

        return $massPart->load(['type', 'prayer', 'massSongs.song']);
    }
}
