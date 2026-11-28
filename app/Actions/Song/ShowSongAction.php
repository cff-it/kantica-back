<?php

namespace App\Actions\Song;

use App\DTOs\Song\SongDetailDTO;
use App\DTOs\Song\SongUsageDTO;
use App\DTOs\Song\SongUseDTO;
use App\Repositories\SongRepository;
use App\Repositories\SongUsageRepository;
use Carbon\Carbon;

// Le cantique avec ses sections et son historique d'usage (éviter les répétitions, équilibrer le répertoire).
// `today()` suit le fuseau de l'application (UTC) : une messe est une date sans heure, l'écart avec Madagascar (UTC+3)
// ne joue que de minuit à 3 h, heures où aucune messe n'est célébrée.
readonly class ShowSongAction
{
    // Les utilisations les plus récentes suffisent pour choisir un chant ; `times_sung` compte toutes les autres
    private const HISTORY_LIMIT = 50;

    public function __construct(
        private SongRepository $songs,
        private SongUsageRepository $usages,
    ) {}

    public function __invoke(int $id): SongDetailDTO
    {
        $song = $this->songs->findWithSections($id);
        $today = today();

        $history = $this->usages->latestUntil($song->id, $today, self::HISTORY_LIMIT)
            ->map(fn (object $use) => new SongUseDTO(
                massId: (int) $use->mass_id,
                date: Carbon::parse($use->date)->toDateString(),
                occasion: $use->liturgical_name,
                part: $use->part,
            ))
            ->values()
            ->all();

        return new SongDetailDTO($song, new SongUsageDTO(
            timesSung: $this->usages->countUntil($song->id, $today),
            lastSung: $history[0] ?? null,
            history: $history,
        ));
    }
}
