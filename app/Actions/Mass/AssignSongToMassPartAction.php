<?php

namespace App\Actions\Mass;

use App\DTOs\Mass\AssignSongToMassPartDTO;
use App\Models\MassSong;
use App\Repositories\MassRepository;
use App\Repositories\SongRepository;

readonly class AssignSongToMassPartAction
{
    public function __construct(
        private MassRepository $masses,
        private SongRepository $songs,
    ) {}

    public function __invoke(AssignSongToMassPartDTO $dto): MassSong
    {
        $this->masses->findPartById($dto->massPartId);
        $this->songs->findById($dto->songId);

        return MassSong::create([
            'mass_part_id'   => $dto->massPartId,
            'song_id'        => $dto->songId,
            'specific_title' => $dto->specificTitle,
        ]);
    }
}
