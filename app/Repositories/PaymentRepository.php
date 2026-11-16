<?php

namespace App\Repositories;

use App\Models\EventPayment;
use App\Models\UserPayment;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository
{
    public function paymentsForEvent(int $eventId): Collection
    {
        return EventPayment::where('event_id', $eventId)->get();
    }

    public function userPaymentsForEvent(int $eventId): Collection
    {
        return UserPayment::with('user')
            ->whereHas('eventPayment', fn ($q) => $q->where('event_id', $eventId))
            ->get();
    }

    public function createEventPayment(array $data): EventPayment
    {
        return EventPayment::create($data);
    }

    public function createUserPayment(array $data): UserPayment
    {
        return UserPayment::create($data);
    }
}
