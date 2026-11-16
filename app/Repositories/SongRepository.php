<?php

namespace App\Repositories;

use App\Models\Song;
use Illuminate\Database\Eloquent\Collection;

class SongRepository
{
    public function all(): Collection
    {
        return Song::orderBy('title')->get();
    }

    public function findById(int $id): Song
    {
        return Song::findOrFail($id);
    }

    public function create(array $data): Song
    {
        return Song::create($data);
    }

    public function update(Song $song, array $data): Song
    {
        $song->update($data);

        return $song->fresh();
    }

    public function delete(Song $song): void
    {
        $song->delete();
    }
}
