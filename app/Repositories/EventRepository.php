<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\EventSubscription;
use Illuminate\Database\Eloquent\Collection;

class EventRepository
{
    public function all(): Collection
    {
        return Event::orderByDesc('date_start')->get();
    }

    public function findById(int $id): Event
    {
        return Event::with(['subscriptions.user', 'payments', 'checks'])->findOrFail($id);
    }

    public function create(array $data): Event
    {
        return Event::create($data);
    }

    public function findSubscription(int $eventId, int $userId): ?EventSubscription
    {
        return EventSubscription::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();
    }
}
