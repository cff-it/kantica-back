<?php

namespace App\DTOs\Event;

use App\Models\Event;
use Illuminate\Support\Collection;

class AttendanceOverviewDTO
{
    /**
     * @param  Collection<int, Event>  $toRecord  « À saisir » : passés ou du jour, sans saisie, du plus récent au plus ancien
     * @param  Collection<int, Event>  $upcoming  « À venir » : les prochains, du plus proche au plus lointain
     * @param  Collection<int, Event>  $recorded  « Déjà saisis » : une page, avec `present_count`
     * @param  array{to_record: int, upcoming: int, recorded: int}  $counts  effectif de chaque groupe (pastilles)
     * @param  int  $attendeesCount  effectif de la liste de saisie (le « 9 » de « 8 / 9 présents »)
     */
    public function __construct(
        public readonly Collection $toRecord,
        public readonly Collection $upcoming,
        public readonly Collection $recorded,
        public readonly array $counts,
        public readonly int $attendeesCount,
        public readonly int $page,
        public readonly int $perPage,
    ) {}
}
