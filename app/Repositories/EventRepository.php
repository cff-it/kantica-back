<?php

namespace App\Repositories;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\EventCheck;
use App\Models\EventSubscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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

    // Verrouille la ligne de l'événement jusqu'à la fin de la transaction. Toute écriture dans `event_checks` (saisie des
    // présences, pointage) le prend : deux écritures simultanées sur un même événement passent l'une après l'autre
    public function findForUpdate(int $id): Event
    {
        return Event::lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): Event
    {
        return Event::create($data);
    }

    // Saisie des présences faite (même à 0 présent) : l'événement passe en « Déjà saisis ». À appeler avec l'événement
    // verrouillé par `findForUpdate`
    public function markAttendancesRecorded(Event $event, Carbon $at): void
    {
        $event->attendances_recorded_at = $at;
        $event->save();
    }

    /**
     * Les trois groupes de l'écran de choix de l'événement (KAN-12) forment une partition : « Déjà saisis » dès que la
     * saisie est posée, sinon « À saisir » jusqu'à la fin du jour en cours et « À venir » après.
     *
     * @return array{to_record: int, upcoming: int, recorded: int}
     */
    public function overviewCounts(Carbon $endOfToday, ?EventType $type): array
    {
        $row = $this->ofType($type)
            ->toBase()
            ->selectRaw(
                'COUNT(CASE WHEN attendances_recorded_at IS NULL AND date_start <= ? THEN 1 END) AS to_record, '
                .'COUNT(CASE WHEN attendances_recorded_at IS NULL AND date_start > ? THEN 1 END) AS upcoming, '
                .'COUNT(attendances_recorded_at) AS recorded',
                [$endOfToday, $endOfToday,],
            )
            ->first();

        return [
            'to_record' => (int) $row->to_record,
            'upcoming'  => (int) $row->upcoming,
            'recorded'  => (int) $row->recorded,
        ];
    }

    // « À saisir » : événements passés ou du jour sans saisie, du plus récent au plus ancien
    public function toRecord(Carbon $endOfToday, ?EventType $type): Collection
    {
        return $this->ofType($type)
            ->whereNull('attendances_recorded_at')
            ->where('date_start', '<=', $endOfToday)
            ->orderByDesc('date_start')
            ->orderByDesc('id')
            ->get();
    }

    // « À venir » : les prochains événements sans saisie, du plus proche au plus lointain
    public function upcoming(Carbon $endOfToday, ?EventType $type, int $limit): Collection
    {
        return $this->ofType($type)
            ->whereNull('attendances_recorded_at')
            ->where('date_start', '>', $endOfToday)
            ->orderBy('date_start')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * « Déjà saisis » : une page, du plus récent au plus ancien. `present_count` ne compte que les présences des membres
     * de la liste de saisie (`$attendeeIds`), comme « 5 / 9 présents » : jamais au-dessus de l'effectif. Un seul comptage
     * SQL pour toute la page, pas de requête par événement.
     */
    public function recorded(?EventType $type, int $page, int $perPage, EloquentBuilder $attendeeIds): Collection
    {
        return $this->ofType($type)
            ->whereNotNull('attendances_recorded_at')
            ->withCount(['checks as present_count' => fn (EloquentBuilder $checks) => $checks->whereIn('user_id', $attendeeIds),])
            ->orderByDesc('date_start')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();
    }

    private function ofType(?EventType $type): EloquentBuilder
    {
        return Event::query()->when($type, fn (EloquentBuilder $query) => $query->where('type', $type->value));
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

        // insertOrIgnore : une présence déjà écrite (par un chemin qui ne prend pas le verrou) donne le résultat voulu,
        // elle ne doit pas faire échouer toute la saisie
        EventCheck::insertOrIgnore(array_map(fn (int $userId) => [
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
