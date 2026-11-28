<?php

namespace App\Repositories;

use App\Models\MassSongSection;
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

    // N'écrit que si une valeur change : une section renvoyée telle quelle ne coûte aucune requête
    public function update(SongSection $section, array $data): void
    {
        $section->fill($data);

        if ($section->isDirty()) {
            $section->save();
        }
    }

    /**
     * Parmi ces sections du cantique, celles qui figurent dans l'ordre d'une messe (`mass_song_sections`).
     * Les sections sont verrouillées d'abord : une messe qui voudrait en reprendre une attend la fin de la transaction
     * (clé étrangère), puis échoue si la section a été supprimée entre-temps, au lieu de la voir disparaître sans bruit.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function idsUsedInMasses(Song $song, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $locked = $song->sections()->whereIn('id', $ids)->lockForUpdate()->pluck('id');

        return MassSongSection::query()
            ->whereIn('song_section_id', $locked)
            ->distinct()
            ->pluck('song_section_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteByIds(Song $song, array $ids): void
    {
        $song->sections()->whereIn('id', $ids)->delete();
    }
}
