<?php

namespace App\Actions\Song;

use App\DTOs\Song\CreateSongDTO;
use App\Models\Song;
use App\Repositories\SongRepository;

readonly class CreateSongAction
{
    public function __construct(private SongRepository $songs) {}

    public function __invoke(CreateSongDTO $dto): Song
    {
        return $this->songs->create([
            'title'     => $dto->title,
            'author'    => $dto->author,
            'reference' => $dto->reference,
            'notes'     => $dto->notes,
        ]);
    }
}
