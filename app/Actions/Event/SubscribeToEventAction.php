<?php

namespace App\Actions\Event;

use App\DTOs\Event\SubscribeToEventDTO;
use App\Models\EventSubscription;
use App\Repositories\EventRepository;
use Illuminate\Validation\ValidationException;

readonly class SubscribeToEventAction
{
    public function __construct(private EventRepository $events) {}

    public function __invoke(SubscribeToEventDTO $dto): EventSubscription
    {
        $this->events->findById($dto->eventId);

        if ($this->events->findSubscription($dto->eventId, $dto->userId)) {
            throw ValidationException::withMessages([
                'user_id' => ['Ce membre est déjà inscrit à cet événement.'],
            ]);
        }

        return EventSubscription::create([
            'event_id'      => $dto->eventId,
            'user_id'       => $dto->userId,
            'subscribed_at' => $dto->subscribedAt,
            'has_paid'      => false,
        ]);
    }
}
