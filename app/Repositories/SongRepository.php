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

    // Répertoire (US-04) : les seules colonnes affichées par la liste, sans tri SQL (ListSongsAction trie en PHP, sans
    // casse ni accents)
    public function allForList(): Collection
    {
        return Song::query()->select(['id', 'title', 'author', 'reference',])->get();
    }

    public function findById(int $id): Song
    {
        return Song::findOrFail($id);
    }

    public function findWithSections(int $id): Song
    {
        return Song::with('sections')->findOrFail($id);
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
