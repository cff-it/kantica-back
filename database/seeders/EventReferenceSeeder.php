<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Models\Event;
use App\Repositories\EventRepository;
use App\Repositories\MemberRepository;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Événements de démonstration de l'écran « Présences » (KAN-12) : des répétitions et des messes passées (saisies ou non),
 * du jour et à venir, un concert, pour voir les trois groupes « À saisir », « À venir » et « Déjà saisis ».
 *
 * Sert au développement et à la recette. Se lance à la main (`php artisan db:seed --class=EventReferenceSeeder`), jamais
 * par `app:deploy`. Les dates sont relatives à la date du premier lancement ; relancé, il ne crée que les intitulés
 * absents et ne modifie ni ne supprime aucun événement. Les présences des événements « saisis » sont prises parmi les
 * membres déjà présents en base (aucune si la base n'en a pas).
 */
class EventReferenceSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: EventType, 2: int, 3: string, 4: string|null, 5: bool}> intitulé, type, jours par
     *                                                                                       rapport à aujourd'hui, heure (fuseau de la chorale), lieu, saisie faite
     */
    private const array EVENTS = [
        ['Répétition du jeudi (démo)', EventType::Repetition, -21, '17:00', 'Salle paroissiale', true,],
        ['Messe dominicale (démo)', EventType::Mass, -14, '09:00', 'Cathédrale d\'Andohalo', true,],
        ['Répétition générale (démo)', EventType::Repetition, -7, '17:00', 'Salle paroissiale', false,],
        ['Messe de l\'Avent (démo)', EventType::Mass, -2, '18:00', 'Cathédrale d\'Andohalo', false,],
        ['Répétition du jour (démo)', EventType::Repetition, 0, '17:00', 'Salle paroissiale', false,],
        ['Messe de Noël (démo)', EventType::Mass, 3, '18:00', 'Cathédrale d\'Andohalo', false,],
        ['Concert de l\'Épiphanie (démo)', EventType::Concert, 10, '16:00', null, false,],
        ['Répétition de rentrée (démo)', EventType::Repetition, 24, '17:00', 'Salle paroissiale', false,],
    ];

    public function run(EventRepository $events, MemberRepository $members): void
    {
        $created = 0;
        $attendeeIds = $members->attendeeIds();
        $today = Carbon::now(config('kantica.timezone'))->startOfDay();

        foreach (self::EVENTS as [$name, $type, $days, $time, $location, $recorded]) {
            [$hour, $minute] = explode(':', $time);
            $dateStart = $today->copy()
                ->addDays($days)
                ->setTime((int) $hour, (int) $minute)
                ->setTimezone(config('app.timezone'));

            $event = Event::firstOrCreate(
                ['name' => $name,],
                ['type' => $type, 'date_start' => $dateStart, 'location' => $location,],
            );

            if (!$event->wasRecentlyCreated) {
                continue;
            }

            $created++;

            if ($recorded) {
                // Environ les deux tiers des membres présents : « 6 / 9 présents »
                $present = array_slice($attendeeIds, 0, (int) ceil(count($attendeeIds) * 2 / 3));
                $events->addAttendances($event->id, $present, $dateStart);
                $events->markAttendancesRecorded($event, $dateStart->copy()->addHours(3));
            }
        }

        $this->command?->info($created.' événement(s) de démonstration créé(s), '.Event::count().' au total.');
    }
}
