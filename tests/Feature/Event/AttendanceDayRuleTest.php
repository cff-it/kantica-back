<?php

namespace Tests\Feature\Event;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\EventCheck;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

/**
 * Règle du jour J et date de saisie (KAN-12) : `GET` et `PUT /api/events/{event}/attendances` répondent 422 avant le jour de
 * l'événement (« Les présences se saisissent à partir du jour de l'événement »), et chaque enregistrement pose
 * `events.attendances_recorded_at`. Le jour se lit dans le fuseau de la chorale (UTC+3), les dates en base sont en UTC.
 */
class AttendanceDayRuleTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    private const string MESSAGE = 'Les présences se saisissent à partir du jour de l\'événement.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
    }

    // ── Règle du jour J ─────────────────────────────────────────────────────

    public function test_the_attendances_of_an_event_after_today_cannot_be_read(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $event = $this->event('2026-11-29 14:00:00');

        $this->getJson("/api/events/{$event->id}/attendances")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event' => self::MESSAGE,])
            ->assertJsonPath('message', self::MESSAGE);
    }

    public function test_the_attendances_of_an_event_after_today_cannot_be_recorded(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $event = $this->event('2026-11-29 14:00:00');

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event' => self::MESSAGE,]);

        $this->assertSame(0, EventCheck::count());
        $this->assertNull($event->fresh()->attendances_recorded_at);
    }

    public function test_an_event_of_today_can_be_read_and_recorded(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $event = $this->event('2026-11-28 14:00:00');

        $this->getJson("/api/events/{$event->id}/attendances")->assertOk();
        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])
            ->assertOk()
            ->assertJsonPath('data.present_count', 1);
    }

    // Une répétition « ce soir » à 18 h reste du jour même si elle est plus tard que l'heure actuelle
    public function test_a_later_hour_of_today_is_still_today(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 05:00:00', 'UTC'));
        $event = $this->event('2026-11-28 15:00:00');

        $this->getJson("/api/events/{$event->id}/attendances")->assertOk();
    }

    // La journée de la chorale finit à 23 h 59 min 59 s à Antananarivo, soit 20 h 59 min 59 s UTC
    public function test_the_day_ends_at_the_last_second_of_the_choir_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $lastSecond = $this->event('2026-11-28 20:59:59');
        $midnight = $this->event('2026-11-28 21:00:00');

        $this->getJson("/api/events/{$lastSecond->id}/attendances")->assertOk();
        $this->getJson("/api/events/{$midnight->id}/attendances")->assertUnprocessable();
    }

    // Le jour est celui de la chorale : à 22 h UTC il est déjà 1 h le lendemain à Antananarivo. Un événement du lendemain
    // à 15 h locale (12 h UTC) est « du jour », un événement d'après-demain à 0 h locale (21 h UTC) ne l'est pas
    public function test_the_day_is_the_one_of_the_choir_and_not_the_one_of_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 22:00:00', 'UTC'));
        $sameDayLocally = $this->event('2026-11-29 12:00:00');
        $nextDayLocally = $this->event('2026-11-29 21:00:00');

        $this->getJson("/api/events/{$sameDayLocally->id}/attendances")->assertOk();
        $this->putJson("/api/events/{$sameDayLocally->id}/attendances", ['present_member_ids' => [],])->assertOk();
        $this->getJson("/api/events/{$nextDayLocally->id}/attendances")->assertUnprocessable();
        $this->putJson("/api/events/{$nextDayLocally->id}/attendances", ['present_member_ids' => [],])->assertUnprocessable();
    }

    public function test_the_timezone_of_the_choir_is_configurable(): void
    {
        config()->set('kantica.timezone', 'UTC');
        $this->travelTo(Carbon::parse('2026-11-28 22:00:00', 'UTC'));
        $event = $this->event('2026-11-29 12:00:00');

        $this->getJson("/api/events/{$event->id}/attendances")->assertUnprocessable();
    }

    public function test_a_past_event_can_still_be_read_corrected_and_recorded_again(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $tiana = $this->member('Andria', 'Tiana');
        $event = $this->event('2026-10-24 14:00:00');

        $this->getJson("/api/events/{$event->id}/attendances")->assertOk();
        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])->assertOk();
        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$tiana->id,],])->assertOk();

        $this->assertSame([$tiana->id,], EventCheck::where('event_id', $event->id)->pluck('user_id')->all());
    }

    // L'événement inconnu garde son 404, et le refus de rôle son 403 : la règle du jour ne les masque pas
    public function test_an_unknown_event_is_still_a_404_and_a_member_still_a_403(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $future = $this->event('2026-12-24 14:00:00');

        $this->getJson('/api/events/9999/attendances')->assertNotFound();

        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $this->getJson("/api/events/{$future->id}/attendances")->assertForbidden();
        $this->putJson("/api/events/{$future->id}/attendances", ['present_member_ids' => [],])->assertForbidden();
    }

    // ── attendances_recorded_at ─────────────────────────────────────────────

    public function test_a_recording_sets_attendances_recorded_at(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $event = $this->event('2026-11-28 08:00:00');
        $this->assertNull($event->attendances_recorded_at);

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])->assertOk();

        $this->assertTrue($event->fresh()->attendances_recorded_at->equalTo(now()));
    }

    // Une saisie à 0 présent reste une saisie
    public function test_an_empty_recording_sets_attendances_recorded_at_too(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $event = $this->event('2026-11-28 08:00:00');

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [],])->assertOk();

        $this->assertNotNull($event->fresh()->attendances_recorded_at);
        $this->assertSame(0, EventCheck::count());
    }

    public function test_every_recording_updates_attendances_recorded_at(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $event = $this->event('2026-11-28 08:00:00');
        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])->assertOk();

        $this->travelTo(Carbon::parse('2026-11-28 12:30:00', 'UTC'));
        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])->assertOk();

        $this->assertSame('2026-11-28 12:30:00', $event->fresh()->attendances_recorded_at->toDateTimeString());
    }

    public function test_reading_does_not_set_attendances_recorded_at(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $event = $this->event('2026-11-28 08:00:00');

        $this->getJson("/api/events/{$event->id}/attendances")->assertOk();

        $this->assertNull($event->fresh()->attendances_recorded_at);
    }

    public function test_a_refused_recording_does_not_set_attendances_recorded_at(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $event = $this->event('2026-11-28 08:00:00');

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id, 99999,],])
            ->assertUnprocessable();
        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => 'oups',])
            ->assertUnprocessable();

        $this->assertNull($event->fresh()->attendances_recorded_at);
        $this->assertSame(0, EventCheck::count());
    }

    public function test_the_attendances_recorded_at_of_other_events_is_not_touched(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'UTC'));
        $event = $this->event('2026-11-28 08:00:00');
        $other = $this->event('2026-11-21 08:00:00');

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [],])->assertOk();

        $this->assertNull($other->fresh()->attendances_recorded_at);
    }

    // ── Aides ───────────────────────────────────────────────────────────────

    private function event(string $dateStart): Event
    {
        return Event::factory()->startingAt($dateStart)->create();
    }
}
