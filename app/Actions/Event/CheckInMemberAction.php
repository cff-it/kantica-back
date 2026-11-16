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

        $existing = EventCheck::where('event_id', $dto->eventId)
            ->where('user_id', $dto->userId)
            ->whereNull('date_out')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'user_id' => ['Ce membre est déjà enregistré en entrée pour cet événement.'],
            ]);
        }

        return EventCheck::create([
            'event_id' => $dto->eventId,
            'user_id'  => $dto->userId,
            'date_in'  => $dto->dateIn,
        ]);
    }
}
