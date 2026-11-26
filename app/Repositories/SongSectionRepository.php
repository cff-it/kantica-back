<?php

namespace App\Repositories;

use App\Models\Song;
use App\Models\SongSection;
use Illuminate\Database\Eloquent\Collection;

class SongSectionRepository
{
    /**
     * @return Collection<int, SongSection>
     */
    public function forSong(Song $song): Collection
    {
        return $song->sections()->get();
    }

    public function create(Song $song, array $data): SongSection
    {
        return $song->sections()->create($data);
    }

    public function update(SongSection $section, array $data): void
    {
        $section->update($data);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteByIds(Song $song, array $ids): void
    {
        $song->sections()->whereIn('id', $ids)->delete();
    }
}
