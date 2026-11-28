<?php

namespace App\Actions\Event;

use App\DTOs\Event\CheckInDTO;
use App\Models\EventCheck;
use App\Repositories\EventRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class CheckInMemberAction
{
    public function __construct(private EventRepository $events) {}

    public function __invoke(CheckInDTO $dto): EventCheck
    {
        try {
            return DB::transaction(function () use ($dto): EventCheck {
                // Même verrou (ligne de l'événement) que la saisie des présences (RecordAttendancesAction) : un pointage
                // et une saisie, ou deux pointages, simultanés passent l'un après l'autre. Pris avant de chercher un doublon.
                $this->events->findForUpdate($dto->eventId);

                // Une seule présence par membre et par événement (KAN-12), sortie ou non : la base refuse le doublon
                $existing = EventCheck::where('event_id', $dto->eventId)
                    ->where('user_id', $dto->userId)
                    ->first();

                if ($existing) {
                    throw $this->alreadyPresent();
                }

                return EventCheck::create([
                    'event_id' => $dto->eventId,
                    'user_id'  => $dto->userId,
                    'date_in'  => $dto->dateIn,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Filet : présence écrite entre le contrôle et l'écriture par un chemin qui ne prend pas le verrou
            throw $this->alreadyPresent();
        }
    }

    private function alreadyPresent(): ValidationException
    {
        return ValidationException::withMessages([
            'user_id' => ['Ce membre est déjà enregistré pour cet événement.',],
        ]);
    }
}
