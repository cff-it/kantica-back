<?php

namespace App\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Les utilisations d'un cantique en messe (`mass_songs`), jusqu'à une date comprise : une messe préparée pour plus tard
// n'est pas encore un chant « chanté ». Une même messe compte autant de fois que le cantique y est chanté.
class SongUsageRepository
{
    public function countUntil(int $songId, CarbonInterface $day): int
    {
        return $this->sungUntil($songId, $day)->count();
    }

    /**
     * Les `$limit` utilisations les plus récentes, triées en SQL : date de la messe décroissante, puis messe créée en
     * dernier d'abord (deux messes le même jour), puis ordre d'enregistrement.
     *
     * @return Collection<int, object{mass_id: int, date: string, liturgical_name: string|null, part: string}>
     */
    public function latestUntil(int $songId, CarbonInterface $day, int $limit): Collection
    {
        return $this->sungUntil($songId, $day)
            ->orderByDesc('masses.date')
            ->orderByDesc('masses.id')
            ->orderBy('mass_songs.id')
            ->limit($limit)
            ->get(['masses.id as mass_id', 'masses.date', 'masses.liturgical_name', 'mass_part_types.name as part',]);
    }

    private function sungUntil(int $songId, CarbonInterface $day): Builder
    {
        return DB::table('mass_songs')
            ->join('mass_parts', 'mass_parts.id', '=', 'mass_songs.mass_part_id')
            ->join('masses', 'masses.id', '=', 'mass_parts.mass_id')
            ->join('mass_part_types', 'mass_part_types.id', '=', 'mass_parts.mass_part_type_id')
            ->where('mass_songs.song_id', $songId)
            ->whereDate('masses.date', '<=', $day);
    }
}
