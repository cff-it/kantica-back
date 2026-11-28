<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\EventCheck;
use App\Models\EventSubscription;
use Carbon\Carbon;
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

    // L'événement seul, sans ses inscriptions, paiements ni présences (en-tête de la saisie des présences)
    public function findWithoutRelations(int $id): Event
    {
        return Event::findOrFail($id);
    }

    // Verrouille la ligne de l'événement jusqu'à la fin de la transaction : deux saisies simultanées passent l'une après l'autre
    public function findForUpdate(int $id): Event
    {
        return Event::lockForUpdate()->findOrFail($id);
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

    /**
     * Identifiants des membres présents à l'événement.
     *
     * @return list<int>
     */
    public function presentUserIds(int $eventId): array
    {
        return EventCheck::where('event_id', $eventId)->pluck('user_id')->all();
    }

    /**
     * @param  list<int>  $userIds
     */
    public function addAttendances(int $eventId, array $userIds, Carbon $dateIn): void
    {
        if ($userIds === []) {
            return;
        }

        EventCheck::insert(array_map(fn (int $userId) => [
            'event_id'   => $eventId,
            'user_id'    => $userId,
            'date_in'    => $dateIn,
            'created_at' => $dateIn,
            'updated_at' => $dateIn,
        ], $userIds));
    }

    /**
     * @param  list<int>  $userIds
     */
    public function removeAttendances(int $eventId, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        EventCheck::where('event_id', $eventId)->whereIn('user_id', $userIds)->delete();
    }
}
