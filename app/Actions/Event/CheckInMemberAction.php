<?php

namespace App\Actions\Event;

use App\DTOs\Event\CheckInDTO;
use App\Models\EventCheck;
use App\Repositories\EventRepository;
use Illuminate\Validation\ValidationException;

readonly class CheckInMemberAction
{
    public function __construct(private EventRepository $events) {}

    public function __invoke(CheckInDTO $dto): EventCheck
    {
        $this->events->findById($dto->eventId);

        // Une seule présence par membre et par événement (KAN-12), sortie ou non : la base refuse le doublon
        $existing = EventCheck::where('event_id', $dto->eventId)
            ->where('user_id', $dto->userId)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'user_id' => ['Ce membre est déjà enregistré pour cet événement.'],
            ]);
        }

        return EventCheck::create([
            'event_id' => $dto->eventId,
            'user_id'  => $dto->userId,
            'date_in'  => $dto->dateIn,
        ]);
    }
}
