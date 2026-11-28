<?php

namespace Tests\Feature\Event;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\EventCheck;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

/**
 * Saisie des présences d'un événement (KAN-12, US-11) : `GET` et `PUT /api/events/{event}/attendances`.
 */
class EventAttendancesTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
    }

    // ── Lecture : GET /api/events/{event}/attendances ───────────────────────

    public function test_a_secretary_reads_the_members_with_their_presence_and_the_counter(): void
    {
        $event = $this->event();
        $randria = $this->member('Randria', 'Mina', 'S');
        $rasoanaivo = $this->member('Rasoanaivo', 'Hanta Zo', 'A');
        $andria = $this->member('Andria', 'Tiana', 'T');
        $this->markPresent($event, $rasoanaivo);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $response = $this->getJson("/api/events/{$event->id}/attendances")
            ->assertOk()
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.event.name', 'Répétition')
            ->assertJsonPath('data.present_count', 1)
            ->assertJsonPath('data.total_count', 4);

        // Tri par nom puis prénom, comme la liste des membres ; la secrétaire connectée est un membre actif comme les autres
        $ids = $response->json('data.members.*.id');
        $this->assertSame([$andria->id, $randria->id, $rasoanaivo->id,], array_values(array_intersect($ids, [$andria->id, $randria->id, $rasoanaivo->id,])));
        $row = fn (int $id) => collect($response->json('data.members'))->firstWhere('id', $id);
        $this->assertSame('Andria', $row($andria->id)['last_name']);
        $this->assertSame('Tiana', $row($andria->id)['first_name']);
        $this->assertSame('T', $row($andria->id)['voice_group']);
        $this->assertFalse($row($andria->id)['present']);
        $this->assertSame('Hanta Zo', $row($rasoanaivo->id)['first_name']);
        $this->assertSame('A', $row($rasoanaivo->id)['voice_group']);
        $this->assertTrue($row($rasoanaivo->id)['present']);
        $this->assertStringStartsWith('2026-10-24T', $response->json('data.event.date_start'));
    }

    public function test_an_admin_reads_the_attendances(): void
    {
        $event = $this->event();
        $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->getJson("/api/events/{$event->id}/attendances")
            ->assertOk()
            ->assertJsonPath('data.total_count', 2);
    }

    public function test_an_event_without_attendance_has_nobody_present(): void
    {
        $event = $this->event();
        $this->member('Rasoanaivo', 'Hanta');
        $this->member('Andria', 'Tiana');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $response = $this->getJson("/api/events/{$event->id}/attendances")
            ->assertOk()
            ->assertJsonPath('data.present_count', 0)
            ->assertJsonPath('data.total_count', 3);

        $this->assertNotContains(true, $response->json('data.members.*.present'));
    }

    public function test_pending_accounts_and_the_original_admin_are_not_listed(): void
    {
        config()->set('kantica.super_admin.mail', 'root@example.com');
        $event = $this->event();
        $this->member('Rasoanaivo', 'Hanta');
        $this->member('Andria', 'Tiana', 'T', UserStatus::Pending);
        User::factory()->create(['mail' => 'root@example.com', 'last_name' => 'Root',])->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $response = $this->getJson("/api/events/{$event->id}/attendances")
            ->assertOk()
            ->assertJsonPath('data.total_count', 2);

        $this->assertContains('Rasoanaivo', $response->json('data.members.*.last_name'));
        $this->assertNotContains('Andria', $response->json('data.members.*.last_name'));
        $this->assertNotContains('Root', $response->json('data.members.*.last_name'));
    }

    public function test_a_member_without_record_is_listed_with_a_null_voice(): void
    {
        $event = $this->event();
        $member = $this->member('Rasoanaivo', 'Hanta', null);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $response = $this->getJson("/api/events/{$event->id}/attendances")->assertOk();

        $row = collect($response->json('data.members'))->firstWhere('id', $member->id);
        $this->assertSame('Rasoanaivo', $row['last_name']);
        $this->assertNull($row['voice_group']);
        $this->assertNull($row['voice']);
    }

    public function test_the_counter_only_counts_listed_members_and_the_event_asked(): void
    {
        $event = $this->event();
        $other = $this->event('Messe dominicale');
        $listed = $this->member('Rasoanaivo', 'Hanta');
        $pending = $this->member('Andria', 'Tiana', 'T', UserStatus::Pending);
        $this->markPresent($event, $listed, $pending);
        $this->markPresent($other, $listed);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->getJson("/api/events/{$event->id}/attendances")
            ->assertOk()
            ->assertJsonPath('data.present_count', 1)
            ->assertJsonPath('data.total_count', 2);
    }

    public function test_a_member_or_an_account_without_role_cannot_read_the_attendances(): void
    {
        $event = $this->event();

        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $this->getJson("/api/events/{$event->id}/attendances")->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/events/{$event->id}/attendances")->assertForbidden();
    }

    public function test_a_guest_cannot_read_the_attendances(): void
    {
        $event = $this->event();

        $this->getJson("/api/events/{$event->id}/attendances")->assertUnauthorized();
    }

    public function test_an_unknown_event_gives_a_404_to_a_secretary_and_a_403_to_a_member(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->getJson('/api/events/9999/attendances')->assertNotFound();

        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $this->getJson('/api/events/9999/attendances')->assertForbidden();
    }

    public function test_a_non_numeric_or_too_large_event_identifier_gives_a_404(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->getJson('/api/events/abc/attendances')->assertNotFound();
        $this->getJson('/api/events/99999999999999999999/attendances')->assertNotFound();
        $this->putJson('/api/events/99999999999999999999/attendances', ['present_member_ids' => [],])->assertNotFound();
    }

    // ── Enregistrement : PUT /api/events/{event}/attendances ────────────────

    public function test_a_secretary_records_the_first_attendances(): void
    {
        $event = $this->event();
        $other = $this->event('Messe dominicale');
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $tiana = $this->member('Andria', 'Tiana');
        $this->member('Randria', 'Mina');
        $this->markPresent($other, $hanta);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id, $tiana->id,],])
            ->assertOk()
            ->assertJsonPath('data.present_count', 2)
            ->assertJsonPath('data.total_count', 4);

        $this->assertEqualsCanonicalizing([$hanta->id, $tiana->id,], $this->presentIds($event));
        $this->assertNotNull(EventCheck::where('event_id', $event->id)->where('user_id', $hanta->id)->value('date_in'));
        // Les présences des autres événements ne bougent pas
        $this->assertSame([$hanta->id,], $this->presentIds($other));
    }

    public function test_an_admin_records_attendances(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])
            ->assertOk()
            ->assertJsonPath('data.present_count', 1);

        $this->assertSame([$hanta->id,], $this->presentIds($event));
    }

    public function test_the_response_lists_the_members_with_their_new_presence(): void
    {
        $event = $this->event();
        $andria = $this->member('Andria', 'Tiana');
        $rasoanaivo = $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $response = $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$rasoanaivo->id,],])
            ->assertOk()
            ->assertJsonPath('data.event.id', $event->id);

        $row = fn (int $id) => collect($response->json('data.members'))->firstWhere('id', $id);
        $this->assertFalse($row($andria->id)['present']);
        $this->assertTrue($row($rasoanaivo->id)['present']);
    }

    public function test_a_recorded_list_can_be_corrected(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $tiana = $this->member('Andria', 'Tiana');
        $mina = $this->member('Randria', 'Mina');
        $this->markPresent($event, $hanta, $tiana);
        $kept = EventCheck::where('event_id', $event->id)->where('user_id', $tiana->id)->first();
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        // Hanta est décochée, Mina cochée, Tiana reste présente
        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$tiana->id, $mina->id,],])
            ->assertOk()
            ->assertJsonPath('data.present_count', 2);

        $this->assertEqualsCanonicalizing([$tiana->id, $mina->id,], $this->presentIds($event));
        // La présence maintenue n'est pas réécrite (même ligne, même heure d'entrée)
        $after = EventCheck::where('event_id', $event->id)->where('user_id', $tiana->id)->first();
        $this->assertSame($kept->id, $after->id);
        $this->assertEquals($kept->date_in, $after->date_in);
    }

    public function test_recording_the_same_list_twice_changes_nothing(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $tiana = $this->member('Andria', 'Tiana');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $payload = ['present_member_ids' => [$hanta->id, $tiana->id,],];

        $this->putJson("/api/events/{$event->id}/attendances", $payload)->assertOk();
        $rows = EventCheck::where('event_id', $event->id)->orderBy('id')->get(['id', 'user_id', 'date_in',])->toArray();

        $this->putJson("/api/events/{$event->id}/attendances", $payload)
            ->assertOk()
            ->assertJsonPath('data.present_count', 2);

        $this->assertSame($rows, EventCheck::where('event_id', $event->id)->orderBy('id')->get(['id', 'user_id', 'date_in',])->toArray());
    }

    public function test_an_empty_list_clears_every_attendance_of_the_event(): void
    {
        $event = $this->event();
        $other = $this->event('Messe dominicale');
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $this->markPresent($event, $hanta);
        $this->markPresent($other, $hanta);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [],])
            ->assertOk()
            ->assertJsonPath('data.present_count', 0);

        $this->assertSame([], $this->presentIds($event));
        $this->assertSame([$hanta->id,], $this->presentIds($other));
    }

    public function test_the_attendance_of_an_account_outside_the_list_is_kept(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $pending = $this->member('Andria', 'Tiana', 'T', UserStatus::Pending);
        $this->markPresent($event, $pending);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])->assertOk();

        $this->assertEqualsCanonicalizing([$hanta->id, $pending->id,], $this->presentIds($event));
    }

    public function test_an_unknown_member_is_refused_and_nothing_is_recorded(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id, 99999,],])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['present_member_ids',]);

        $this->assertSame([], $this->presentIds($event));
    }

    public function test_a_pending_account_and_the_original_admin_cannot_be_marked_present(): void
    {
        config()->set('kantica.super_admin.mail', 'root@example.com');
        $event = $this->event();
        $pending = $this->member('Andria', 'Tiana', 'T', UserStatus::Pending);
        $root = User::factory()->create(['mail' => 'root@example.com',]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$pending->id,],])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['present_member_ids',]);
        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$root->id,],])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['present_member_ids',]);

        $this->assertSame([], $this->presentIds($event));
    }

    public function test_a_member_listed_twice_is_refused(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id, $hanta->id,],])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['present_member_ids.0', 'present_member_ids.1',]);

        $this->assertSame([], $this->presentIds($event));
    }

    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_payload_is_refused(array $payload, string $errorKey): void
    {
        $event = $this->event();
        $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/events/{$event->id}/attendances", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorKey,]);

        $this->assertSame([], $this->presentIds($event));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'liste absente'         => [[], 'present_member_ids',],
            'liste null'            => [['present_member_ids' => null,], 'present_member_ids',],
            'pas un tableau'        => [['present_member_ids' => 5,], 'present_member_ids',],
            'identifiant texte'     => [['present_member_ids' => ['abc',],], 'present_member_ids.0',],
            'identifiant décimal'   => [['present_member_ids' => [1.5,],], 'present_member_ids.0',],
            'identifiant nul'       => [['present_member_ids' => [0,],], 'present_member_ids.0',],
            'identifiant négatif'   => [['present_member_ids' => [-3,],], 'present_member_ids.0',],
            'identifiant trop long' => [['present_member_ids' => ['99999999999999999999',],], 'present_member_ids.0',],
            'liste trop longue'     => [['present_member_ids' => range(1, 1001),], 'present_member_ids',],
        ];
    }

    public function test_a_member_cannot_record_attendances(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Member));

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])
            ->assertForbidden();

        $this->assertSame([], $this->presentIds($event));
    }

    public function test_a_guest_cannot_record_attendances(): void
    {
        $event = $this->event();

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [],])->assertUnauthorized();
    }

    public function test_recording_on_an_unknown_event_gives_a_404_to_a_secretary_and_a_403_to_a_member(): void
    {
        $hanta = $this->member('Rasoanaivo', 'Hanta');

        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->putJson('/api/events/9999/attendances', ['present_member_ids' => [$hanta->id,],])->assertNotFound();

        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $this->putJson('/api/events/9999/attendances', ['present_member_ids' => [$hanta->id,],])->assertForbidden();
    }

    public function test_recording_locks_the_event_row_on_postgresql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Le verrou FOR UPDATE n\'existe pas sous SQLite : à vérifier sous PostgreSQL (CI).');
        }

        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->putJson("/api/events/{$event->id}/attendances", ['present_member_ids' => [$hanta->id,],])->assertOk();

        $this->assertNotEmpty(array_filter($queries, fn (string $sql) => str_contains(strtolower($sql), 'for update')));
    }

    // ── Base de données ─────────────────────────────────────────────────────

    public function test_the_database_refuses_a_second_attendance_of_a_member_for_the_same_event(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $this->markPresent($event, $hanta);

        $this->expectException(QueryException::class);

        $this->markPresent($event, $hanta);
    }

    public function test_the_unique_migration_keeps_the_first_attendance_of_existing_duplicates(): void
    {
        $event = $this->event();
        $hanta = $this->member('Rasoanaivo', 'Hanta');
        $tiana = $this->member('Andria', 'Tiana');

        $migration = require database_path('migrations/2026_11_28_000000_add_unique_event_user_to_event_checks_table.php');
        $migration->down();

        $now = now();
        $row = fn (int $userId) => [
            'event_id'   => $event->id,
            'user_id'    => $userId,
            'date_in'    => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        DB::table('event_checks')->insert([$row($hanta->id), $row($hanta->id), $row($tiana->id), $row($hanta->id),]);
        $firstOfHanta = DB::table('event_checks')->where('user_id', $hanta->id)->min('id');

        $migration->up();

        $this->assertSame(2, DB::table('event_checks')->count());
        $this->assertEquals([$firstOfHanta,], DB::table('event_checks')->where('user_id', $hanta->id)->pluck('id')->all());
        $this->assertSame(1, DB::table('event_checks')->where('user_id', $tiana->id)->count());
    }

    // ── Aides ───────────────────────────────────────────────────────────────

    private function event(string $name = 'Répétition'): Event
    {
        return Event::create(['name' => $name, 'date_start' => '2026-10-24 17:00:00',]);
    }

    private function markPresent(Event $event, User ...$members): void
    {
        foreach ($members as $member) {
            EventCheck::create(['event_id' => $event->id, 'user_id' => $member->id, 'date_in' => now(),]);
        }
    }

    /**
     * @return list<int>
     */
    private function presentIds(Event $event): array
    {
        return EventCheck::where('event_id', $event->id)->pluck('user_id')->all();
    }
}
