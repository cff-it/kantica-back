<?php

namespace Tests\Feature\Event;

use App\Enums\EventType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\EventCheck;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

/**
 * Écran de choix de l'événement des présences (KAN-12) : `GET /api/events/attendance-overview`, en trois groupes
 * (« À saisir », « À venir », « Déjà saisis ») avec leurs décomptes.
 *
 * Horloge figée au 28 novembre 2026 à 10 h UTC, soit 13 h à Antananarivo (UTC+3) : la journée de la chorale se termine à
 * 20 h 59 min 59 s UTC.
 */
class AttendanceOverviewTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
    }

    // ── Les trois groupes ───────────────────────────────────────────────────

    public function test_events_are_split_in_three_groups_and_sorted(): void
    {
        $recordedOld = $this->event('2026-11-14 14:00:00', 'Répétition saisie', recorded: true);
        $recordedRecent = $this->event('2026-11-21 14:00:00', 'Messe saisie', EventType::Mass, recorded: true);
        $past = $this->event('2026-11-21 14:00:00', 'Répétition passée');
        $todayMorning = $this->event('2026-11-28 05:00:00', 'Répétition du matin');
        $todayLastSecond = $this->event('2026-11-28 20:59:59', 'Messe de la soirée', EventType::Mass);
        $tomorrowMidnight = $this->event('2026-11-28 21:00:00', 'Messe de minuit', EventType::Mass);
        $nextWeek = $this->event('2026-12-05 14:00:00', 'Concert', EventType::Concert);

        $response = $this->getJson('/api/events/attendance-overview')->assertOk();

        // « À saisir » : passés et du jour sans saisie, du plus récent au plus ancien. Le jour finit à 23 h 59 min 59 s à
        // Antananarivo : 20 h 59 min 59 s UTC encore « du jour », 21 h 00 min 00 s UTC déjà « à venir »
        $this->assertSame(
            [$todayLastSecond->id, $todayMorning->id, $past->id,],
            $response->json('data.to_record.*.id'),
        );
        $this->assertSame([$tomorrowMidnight->id, $nextWeek->id,], $response->json('data.upcoming.*.id'));
        $this->assertSame([$recordedRecent->id, $recordedOld->id,], $response->json('data.recorded.*.id'));
    }

    // Une saisie à 0 présent reste une saisie : c'est le champ attendances_recorded_at, pas le nombre de présences
    public function test_an_event_recorded_with_nobody_present_is_in_the_recorded_group(): void
    {
        $this->member('Rasoanaivo', 'Hanta');
        $nobody = $this->event('2026-11-21 14:00:00', 'Répétition vide', recorded: true);
        $untouched = $this->event('2026-11-14 14:00:00', 'Répétition jamais saisie');

        $response = $this->getJson('/api/events/attendance-overview')->assertOk();

        $this->assertSame([$nobody->id,], $response->json('data.recorded.*.id'));
        $this->assertSame(0, $response->json('data.recorded.0.present_count'));
        $this->assertSame([$untouched->id,], $response->json('data.to_record.*.id'));
    }

    public function test_the_groups_never_overlap_even_for_a_recorded_event_in_the_future(): void
    {
        $future = $this->event('2026-12-05 14:00:00', 'Déjà saisi malgré tout', recorded: true);

        $response = $this->getJson('/api/events/attendance-overview')->assertOk();

        $this->assertSame([$future->id,], $response->json('data.recorded.*.id'));
        $this->assertSame([], $response->json('data.upcoming'));
        $this->assertSame([], $response->json('data.to_record'));
        $this->assertSame(['to_record' => 0, 'upcoming' => 0, 'recorded' => 1,], $response->json('meta.counts'));
    }

    public function test_an_empty_overview_has_three_empty_groups(): void
    {
        $this->getJson('/api/events/attendance-overview')
            ->assertOk()
            ->assertExactJson([
                'data' => ['to_record' => [], 'upcoming' => [], 'recorded' => [],],
                'meta' => [
                    'counts'    => ['to_record' => 0, 'upcoming' => 0, 'recorded' => 0,],
                    'page'      => 1,
                    'per_page'  => 20,
                    'last_page' => 1,
                ],
            ]);
    }

    public function test_the_counts_are_the_size_of_each_group(): void
    {
        $this->event('2026-11-14 14:00:00', 'Saisie', recorded: true);
        $this->event('2026-11-15 14:00:00', 'Saisie bis', recorded: true);
        $this->event('2026-11-21 14:00:00', 'Passée');
        $this->event('2026-11-28 05:00:00', 'Du jour');
        $this->event('2026-11-29 14:00:00', 'Demain');
        $this->event('2026-12-05 14:00:00', 'Dans huit jours');

        $this->getJson('/api/events/attendance-overview')
            ->assertOk()
            ->assertJsonPath('meta.counts', ['to_record' => 2, 'upcoming' => 2, 'recorded' => 2,]);
    }

    // ── Contenu d'une ligne ─────────────────────────────────────────────────

    public function test_each_event_has_the_fields_of_the_screen(): void
    {
        $this->event('2026-11-21 14:00:00', 'Répétition passée', location: 'Salle paroissiale');
        $this->event('2026-12-05 14:00:00', 'Concert', EventType::Concert, location: null);

        $response = $this->getJson('/api/events/attendance-overview')->assertOk();

        $toRecord = $response->json('data.to_record.0');
        $this->assertSame(['id', 'name', 'type', 'date_start', 'location',], array_keys($toRecord));
        $this->assertSame('Répétition passée', $toRecord['name']);
        $this->assertSame('repetition', $toRecord['type']);
        $this->assertSame('2026-11-21T14:00:00+00:00', $toRecord['date_start']);
        $this->assertSame('Salle paroissiale', $toRecord['location']);
        $this->assertNull($response->json('data.upcoming.0.location'));
        $this->assertSame('concert', $response->json('data.upcoming.0.type'));
    }

    public function test_a_recorded_event_shows_its_presences_over_the_size_of_the_list(): void
    {
        // Effectif de la liste de saisie : la secrétaire connectée, Hanta et Tiana (en attente) ; Root n'en fait pas partie
        config()->set('kantica.super_admin.mail', 'root@example.com');
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $tiana = $this->member('Andria', 'Tiana', 'T', UserStatus::Pending);
        $root = User::factory()->create(['mail' => 'root@example.com',]);
        $event = $this->event('2026-11-21 14:00:00', 'Répétition saisie', recorded: true);
        $other = $this->event('2026-11-14 14:00:00', 'Autre saisie', recorded: true);
        $this->markPresent($event, $hanta, $tiana, $root);
        $this->markPresent($other, $hanta);

        $response = $this->getJson('/api/events/attendance-overview')->assertOk();

        // Les présences de Hanta et de Tiana comptent, pas celle du compte administrateur originel
        $this->assertSame([2, 1,], $response->json('data.recorded.*.present_count'));
        $this->assertSame([3, 3,], $response->json('data.recorded.*.total'));
    }

    public function test_only_the_recorded_group_has_presence_counts(): void
    {
        $this->event('2026-11-21 14:00:00', 'Passée');
        $this->event('2026-12-05 14:00:00', 'À venir');

        $response = $this->getJson('/api/events/attendance-overview')->assertOk();

        $this->assertArrayNotHasKey('present_count', $response->json('data.to_record.0'));
        $this->assertArrayNotHasKey('total', $response->json('data.upcoming.0'));
    }

    // ── Filtre par type ─────────────────────────────────────────────────────

    public function test_the_type_filter_applies_to_every_group_and_to_the_counts(): void
    {
        $this->event('2026-11-14 14:00:00', 'Répétition saisie', recorded: true);
        $massRecorded = $this->event('2026-11-15 14:00:00', 'Messe saisie', EventType::Mass, recorded: true);
        $this->event('2026-11-21 14:00:00', 'Répétition passée');
        $massPast = $this->event('2026-11-22 14:00:00', 'Messe passée', EventType::Mass);
        $massNext = $this->event('2026-12-05 14:00:00', 'Messe à venir', EventType::Mass);
        $this->event('2026-12-06 14:00:00', 'Concert', EventType::Concert);

        $response = $this->getJson('/api/events/attendance-overview?type=messe')->assertOk();

        $this->assertSame([$massPast->id,], $response->json('data.to_record.*.id'));
        $this->assertSame([$massNext->id,], $response->json('data.upcoming.*.id'));
        $this->assertSame([$massRecorded->id,], $response->json('data.recorded.*.id'));
        $this->assertSame(['to_record' => 1, 'upcoming' => 1, 'recorded' => 1,], $response->json('meta.counts'));
    }

    public function test_every_type_can_be_filtered(): void
    {
        foreach (EventType::cases() as $type) {
            $this->event('2026-11-21 14:00:00', 'Événement '.$type->value, $type);
        }

        foreach (EventType::cases() as $type) {
            $this->getJson('/api/events/attendance-overview?type='.$type->value)
                ->assertOk()
                ->assertJsonCount(1, 'data.to_record')
                ->assertJsonPath('data.to_record.0.type', $type->value);
        }
    }

    public function test_without_type_or_with_an_empty_one_every_type_is_listed(): void
    {
        $this->event('2026-11-21 14:00:00', 'Répétition');
        $this->event('2026-11-22 14:00:00', 'Messe', EventType::Mass);

        $this->getJson('/api/events/attendance-overview')->assertOk()->assertJsonCount(2, 'data.to_record');
        $this->getJson('/api/events/attendance-overview?type=')->assertOk()->assertJsonCount(2, 'data.to_record');
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $this->getJson('/api/events/attendance-overview?type=chorale')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Le type doit être l\'un de : repetition, messe, concert, autre.',]);
        $this->getJson('/api/events/attendance-overview?type[]=messe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type',]);
    }

    // ── Bornes et pagination ────────────────────────────────────────────────

    public function test_the_upcoming_group_only_keeps_the_next_ten_events_but_counts_them_all(): void
    {
        foreach (range(1, 12) as $day) {
            $this->event(Carbon::parse('2026-11-30 14:00:00')->addDays($day)->toDateTimeString(), 'À venir '.$day);
        }

        $response = $this->getJson('/api/events/attendance-overview')->assertOk();

        $this->assertCount(10, $response->json('data.upcoming'));
        $this->assertSame('À venir 1', $response->json('data.upcoming.0.name'));
        $this->assertSame('À venir 10', $response->json('data.upcoming.9.name'));
        $this->assertSame(12, $response->json('meta.counts.upcoming'));
    }

    public function test_the_recorded_group_is_paginated_by_twenty(): void
    {
        foreach (range(1, 25) as $day) {
            $this->event(Carbon::parse('2026-10-01 14:00:00')->addDays($day)->toDateTimeString(), 'Saisi '.$day, recorded: true);
        }

        $first = $this->getJson('/api/events/attendance-overview')->assertOk();
        $second = $this->getJson('/api/events/attendance-overview?page=2')->assertOk();
        $third = $this->getJson('/api/events/attendance-overview?page=3')->assertOk();

        $this->assertCount(20, $first->json('data.recorded'));
        $this->assertSame('Saisi 25', $first->json('data.recorded.0.name'));
        $this->assertSame('Saisi 6', $first->json('data.recorded.19.name'));
        $this->assertSame(['Saisi 5', 'Saisi 4', 'Saisi 3', 'Saisi 2', 'Saisi 1',], $second->json('data.recorded.*.name'));
        $this->assertSame([], $third->json('data.recorded'));
        $this->assertSame(25, $first->json('meta.counts.recorded'));
        $this->assertSame(['page' => 2, 'per_page' => 20, 'last_page' => 2,], [
            'page'      => $second->json('meta.page'),
            'per_page'  => $second->json('meta.per_page'),
            'last_page' => $second->json('meta.last_page'),
        ]);
    }

    public function test_the_page_only_moves_the_recorded_group(): void
    {
        $this->event('2026-11-21 14:00:00', 'Passée');
        $this->event('2026-12-05 14:00:00', 'À venir');

        $this->getJson('/api/events/attendance-overview?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.to_record')
            ->assertJsonCount(1, 'data.upcoming')
            ->assertJsonCount(0, 'data.recorded');
    }

    // ── Requêtes ────────────────────────────────────────────────────────────

    // Ni N+1 ni requête par événement : le nombre de requêtes ne dépend pas du nombre d'événements
    public function test_the_number_of_queries_does_not_depend_on_the_number_of_events(): void
    {
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $this->overviewQueryCount();
        foreach (range(1, 3) as $day) {
            $this->markPresent($this->event('2026-11-0'.$day.' 14:00:00', 'Saisi '.$day, recorded: true), $hanta);
        }
        $this->event('2026-11-21 14:00:00', 'Passée');
        $this->event('2026-12-05 14:00:00', 'À venir');
        $few = $this->overviewQueryCount();

        foreach (range(10, 24) as $day) {
            $this->markPresent($this->event('2026-11-'.$day.' 14:00:00', 'Saisi '.$day, recorded: true), $hanta);
            $this->event('2026-12-'.$day.' 14:00:00', 'À venir '.$day);
        }
        $many = $this->overviewQueryCount();

        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(5, $many);
    }

    public function test_an_event_recorded_by_the_attendance_screen_moves_to_the_recorded_group(): void
    {
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $this->member('Andria', 'Tiana');
        $event = $this->event('2026-11-21 14:00:00', 'Répétition passée');

        $before = $this->getJson('/api/events/attendance-overview')->assertOk();
        $this->assertSame([$event->id,], $before->json('data.to_record.*.id'));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])->assertOk();

        $after = $this->getJson('/api/events/attendance-overview')->assertOk();
        $this->assertSame([], $after->json('data.to_record'));
        $this->assertSame([$event->id,], $after->json('data.recorded.*.id'));
        $this->assertSame(1, $after->json('data.recorded.0.present_count'));
        $this->assertSame(3, $after->json('data.recorded.0.total'));
        $this->assertSame(['to_record' => 0, 'upcoming' => 0, 'recorded' => 1,], $after->json('meta.counts'));
    }

    // ── Droits ──────────────────────────────────────────────────────────────

    public function test_an_admin_reads_the_overview(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->getJson('/api/events/attendance-overview')->assertOk();
    }

    public function test_a_member_or_an_account_without_role_cannot_read_the_overview(): void
    {
        foreach ([$this->userWithRole(RoleName::Member), User::factory()->create(),] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/events/attendance-overview')->assertForbidden();
        }
    }

    public function test_a_guest_cannot_read_the_overview(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/events/attendance-overview')->assertUnauthorized();
    }

    // La route est déclarée avant `/{event}` : « attendance-overview » n'est pas pris pour un identifiant
    public function test_the_other_event_routes_still_work(): void
    {
        $event = $this->event('2026-11-21 14:00:00', 'Répétition passée', location: 'Salle paroissiale');

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'repetition')
            ->assertJsonPath('data.location', 'Salle paroissiale');
        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'repetition');
    }

    // ── Aides ───────────────────────────────────────────────────────────────

    private function event(
        string $dateStart,
        string $name,
        EventType $type = EventType::Repetition,
        bool $recorded = false,
        ?string $location = 'Salle paroissiale',
    ): Event {
        $factory = Event::factory()->ofType($type, $name)->startingAt($dateStart);

        return $factory
            ->state(['location' => $location,])
            ->when($recorded, fn ($factory) => $factory->recorded($dateStart))
            ->create();
    }

    private function markPresent(Event $event, User ...$members): void
    {
        foreach ($members as $member) {
            EventCheck::create(['event_id' => $event->id, 'user_id' => $member->id, 'date_in' => now(),]);
        }
    }

    private function overviewQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/events/attendance-overview')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
