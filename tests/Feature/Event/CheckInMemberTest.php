<?php

namespace Tests\Feature\Event;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\EventCheck;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

/**
 * `POST /api/events/{event}/check-in` : une seule présence par membre et par événement (KAN-12), sortie enregistrée ou non.
 */
class CheckInMemberTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
    }

    public function test_a_member_is_checked_in_once(): void
    {
        $event = Event::create(['name' => 'Répétition', 'date_start' => '2026-10-24 17:00:00',]);
        $member = $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson("/api/events/{$event->id}/check-in", ['user_id' => $member->id,])->assertCreated();
        $this->postJson("/api/events/{$event->id}/check-in", ['user_id' => $member->id,])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id',]);

        $this->assertSame(1, EventCheck::where('event_id', $event->id)->count());
    }

    public function test_a_member_already_checked_out_is_refused_instead_of_failing_on_the_unique_constraint(): void
    {
        $event = Event::create(['name' => 'Répétition', 'date_start' => '2026-10-24 17:00:00',]);
        $member = $this->member('Rasoanaivo', 'Hanta');
        EventCheck::create([
            'event_id' => $event->id,
            'user_id'  => $member->id,
            'date_in'  => now()->subHour(),
            'date_out' => now(),
        ]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson("/api/events/{$event->id}/check-in", ['user_id' => $member->id,])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id',]);

        $this->assertSame(1, EventCheck::where('event_id', $event->id)->count());
    }
}
