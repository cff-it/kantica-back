<?php

namespace Tests\Feature\Event;

use App\Enums\EventType;
use App\Enums\RoleName;
use App\Models\Event;
use App\Models\EventCheck;
use Carbon\Carbon;
use Database\Seeders\EventReferenceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

/**
 * Événements de démonstration de l'écran « Présences » (KAN-12) : `php artisan db:seed --class=EventReferenceSeeder`.
 */
class EventReferenceSeederTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
    }

    public function test_the_demo_events_fill_the_three_groups_of_the_overview(): void
    {
        $this->seed(EventReferenceSeeder::class);

        $this->assertSame(8, Event::count());
        $this->assertEqualsCanonicalizing(
            ['repetition', 'messe', 'concert',],
            Event::pluck('type')->map(fn (EventType $type) => $type->value)->unique()->all(),
        );

        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $response = $this->getJson('/api/events/attendance-overview')->assertOk();

        // Deux événements saisis, trois passés ou du jour à saisir, trois à venir
        $this->assertSame(['to_record' => 3, 'upcoming' => 3, 'recorded' => 2,], $response->json('meta.counts'));
        $this->assertCount(3, $response->json('data.to_record'));
        $this->assertCount(3, $response->json('data.upcoming'));
        $this->assertCount(2, $response->json('data.recorded'));
    }

    public function test_the_recorded_demo_events_have_presences_among_the_members(): void
    {
        $this->member('Rasoanaivo', 'Hanta');
        $this->member('Andria', 'Tiana');
        $this->member('Randria', 'Mina');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->seed(EventReferenceSeeder::class);

        // Quatre membres dans la liste (la secrétaire connectée comprise) : les deux tiers, soit 3, sont présents
        $response = $this->getJson('/api/events/attendance-overview')->assertOk();
        $this->assertSame([3, 3,], $response->json('data.recorded.*.present_count'));
        $this->assertSame([4, 4,], $response->json('data.recorded.*.total'));
    }

    public function test_the_demo_events_are_in_the_choir_timezone(): void
    {
        $this->seed(EventReferenceSeeder::class);

        // 17 h à Antananarivo, soit 14 h UTC
        $this->assertSame(
            '2026-11-28 14:00:00',
            Event::where('name', 'Répétition du jour (démo)')->sole()->date_start->toDateTimeString(),
        );
    }

    public function test_running_the_seeder_again_changes_nothing(): void
    {
        $this->member('Rasoanaivo', 'Hanta');
        $this->seed(EventReferenceSeeder::class);
        $events = Event::orderBy('id')->get(['id', 'name', 'date_start', 'attendances_recorded_at',])->toArray();
        $checks = EventCheck::count();

        $this->travelTo(Carbon::parse('2026-12-10 10:00:00', 'UTC'));
        $this->seed(EventReferenceSeeder::class);

        $this->assertSame(8, Event::count());
        $this->assertSame($events, Event::orderBy('id')->get(['id', 'name', 'date_start', 'attendances_recorded_at',])->toArray());
        $this->assertSame($checks, EventCheck::count());
    }

    public function test_an_existing_event_is_never_modified(): void
    {
        $event = Event::factory()->create(['name' => 'Répétition du jeudi (démo)', 'location' => 'Ailleurs',]);

        $this->seed(EventReferenceSeeder::class);

        $this->assertSame(8, Event::count());
        $this->assertSame('Ailleurs', $event->fresh()->location);
        $this->assertNull($event->fresh()->attendances_recorded_at);
    }
}
