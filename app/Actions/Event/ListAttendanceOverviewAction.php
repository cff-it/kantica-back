<?php

namespace App\Actions\Event;

use App\DTOs\Event\AttendanceOverviewDTO;
use App\DTOs\Event\ListAttendanceOverviewDTO;
use App\Repositories\EventRepository;
use App\Repositories\MemberRepository;
use App\Support\ChoirTime;

/**
 * Écran de choix de l'événement des présences (KAN-12) : « À saisir », « À venir » et « Déjà saisis », avec le décompte de
 * chaque groupe. Cinq requêtes quel que soit le nombre d'événements : décomptes, un groupe chacun, effectif de la liste.
 */
readonly class ListAttendanceOverviewAction
{
    public const int PER_PAGE = 20;

    // Seuls les prochains événements s'affichent : « À venir » ne doit pas devenir l'agenda de toute l'année
    public const int UPCOMING_LIMIT = 10;

    public function __construct(
        private EventRepository $events,
        private MemberRepository $members,
    ) {}

    public function __invoke(ListAttendanceOverviewDTO $dto): AttendanceOverviewDTO
    {
        $endOfToday = ChoirTime::endOfToday();

        return new AttendanceOverviewDTO(
            toRecord: $this->events->toRecord($endOfToday, $dto->type)->toBase(),
            upcoming: $this->events->upcoming($endOfToday, $dto->type, self::UPCOMING_LIMIT)->toBase(),
            recorded: $this->events->recorded(
                $dto->type,
                $dto->page,
                self::PER_PAGE,
                $this->members->attendeeIdsQuery(),
            )->toBase(),
            counts: $this->events->overviewCounts($endOfToday, $dto->type),
            attendeesCount: $this->members->countAttendees(),
            page: $dto->page,
            perPage: self::PER_PAGE,
        );
    }
}
