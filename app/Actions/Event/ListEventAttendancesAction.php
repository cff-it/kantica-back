<?php

namespace App\Actions\Event;

use App\DTOs\Event\EventAttendanceListDTO;
use App\Repositories\EventRepository;
use App\Repositories\MemberRepository;

/**
 * Liste de saisie des présences d'un événement (US-11) : tous les membres actifs, chacun avec son état présent ou non.
 */
readonly class ListEventAttendancesAction
{
    public function __construct(
        private EventRepository $events,
        private MemberRepository $members,
    ) {}

    public function __invoke(int $eventId): EventAttendanceListDTO
    {
        $event = $this->events->findWithoutRelations($eventId);
        $members = $this->members->allActive();
        $present = array_flip($this->events->presentUserIds($eventId));

        // Seuls les membres de la liste comptent : « 5 / 9 présents » ne peut pas dépasser le total affiché
        $presentIds = $members
            ->pluck('id')
            ->filter(fn (int $id) => isset($present[$id]))
            ->values()
            ->all();

        return new EventAttendanceListDTO($event, $members, $presentIds);
    }
}
