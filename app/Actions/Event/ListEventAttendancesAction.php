<?php

namespace App\Actions\Event;

use App\DTOs\Event\EventAttendanceListDTO;
use App\Repositories\EventRepository;
use App\Repositories\MemberRepository;

/**
 * Liste de saisie des présences d'un événement (US-11) : tous les membres actifs et les comptes en attente, chacun avec
 * son état présent ou non. Refusée (422) avant le jour de l'événement (règle du jour J, EnsureAttendancesOpenAction).
 */
readonly class ListEventAttendancesAction
{
    public function __construct(
        private EventRepository $events,
        private MemberRepository $members,
        private EnsureAttendancesOpenAction $ensureOpen,
    ) {}

    public function __invoke(int $eventId): EventAttendanceListDTO
    {
        $event = $this->events->findWithoutRelations($eventId);
        ($this->ensureOpen)($event);

        $members = $this->members->allAttendees();
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
