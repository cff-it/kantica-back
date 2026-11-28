<?php

namespace App\Actions\Event;

use App\DTOs\Event\EventAttendanceListDTO;
use App\DTOs\Event\RecordAttendancesDTO;
use App\Repositories\EventRepository;
use App\Repositories\MemberRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enregistre les présences d'un événement (US-11) : le front envoie la liste complète des présents, et la base est
 * synchronisée dans une transaction (présences manquantes ajoutées, présences décochées supprimées). L'appel est
 * idempotent : le renvoyer, ou envoyer une liste corrigée, ne crée jamais de doublon. La contrainte d'unicité
 * (événement, membre) garantit en base qu'un membre n'est présent qu'une fois. Le pointage (CheckInMemberAction) prend
 * le même verrou, et l'écriture en bloc ignore une présence déjà écrite : une course ne fait pas échouer la saisie.
 *
 * Seuls les membres de la liste de saisie (actifs et en attente) sont concernés : un identifiant inconnu ou du compte
 * administrateur originel est refusé, et une présence d'un compte hors liste n'est jamais supprimée. Refusée (422) avant
 * le jour de l'événement (règle du jour J). Chaque enregistrement, même à 0 présent, pose `attendances_recorded_at` : c'est
 * ce champ qui fait passer l'événement en « Déjà saisis ».
 */
readonly class RecordAttendancesAction
{
    public function __construct(
        private EventRepository $events,
        private MemberRepository $members,
        private ListEventAttendancesAction $list,
        private EnsureAttendancesOpenAction $ensureOpen,
    ) {}

    public function __invoke(RecordAttendancesDTO $dto): EventAttendanceListDTO
    {
        DB::transaction(function () use ($dto) {
            // Verrou pris avant de lire les présences : deux saisies simultanées passent l'une après l'autre
            $event = $this->events->findForUpdate($dto->eventId);
            ($this->ensureOpen)($event);

            $attendeeIds = $this->members->attendeeIds();
            $unknownIds = array_values(array_diff($dto->presentMemberIds, $attendeeIds));

            if ($unknownIds !== []) {
                throw ValidationException::withMessages([
                    'present_member_ids' => ['Membre inconnu : '.implode(', ', $unknownIds).'.',],
                ]);
            }

            $alreadyPresent = $this->events->presentUserIds($dto->eventId);
            $now = now();

            $this->events->removeAttendances(
                $dto->eventId,
                array_values(array_diff(array_intersect($alreadyPresent, $attendeeIds), $dto->presentMemberIds)),
            );
            $this->events->addAttendances(
                $dto->eventId,
                array_values(array_diff($dto->presentMemberIds, $alreadyPresent)),
                $now,
            );
            $this->events->markAttendancesRecorded($event, $now);
        });

        return ($this->list)($dto->eventId);
    }
}
