<?php

namespace Tests\Feature\Event;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migration `type`, `location` et `attendances_recorded_at` de la table `events` (KAN-12) : les événements existants
 * prennent le type « autre », et ceux qui ont déjà des présences comptent comme saisis (sinon ils réapparaîtraient dans
 * « À saisir »).
 */
class EventColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string MIGRATION = 'migrations/2026_11_28_100000_add_type_location_and_attendances_recorded_at_to_events_table.php';

    public function test_existing_events_get_the_other_type_and_the_ones_with_attendances_count_as_recorded(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('events', 'type'));

        $now = now();
        $event = fn (string $name) => DB::table('events')->insertGetId([
            'name'       => $name,
            'date_start' => '2026-10-24 14:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $withAttendances = $event('Avec présences');
        $withoutAttendances = $event('Sans présence');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $check = fn (int $eventId, int $userId, string $createdAt) => [
            'event_id'   => $eventId,
            'user_id'    => $userId,
            'date_in'    => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
        DB::table('event_checks')->insert([
            $check($withAttendances, $user->id, '2026-10-24 15:00:00'),
            $check($withAttendances, $other->id, '2026-10-24 16:30:00'),
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasColumns('events', ['type', 'location', 'attendances_recorded_at',]));
        $this->assertSame('autre', DB::table('events')->where('id', $withAttendances)->value('type'));
        $this->assertSame('autre', DB::table('events')->where('id', $withoutAttendances)->value('type'));
        $this->assertNull(DB::table('events')->where('id', $withAttendances)->value('location'));
        // Date de la saisie : la dernière présence écrite
        $this->assertSame(
            '2026-10-24 16:30:00',
            DB::table('events')->where('id', $withAttendances)->value('attendances_recorded_at'),
        );
        $this->assertNull(DB::table('events')->where('id', $withoutAttendances)->value('attendances_recorded_at'));
    }

    public function test_the_migration_can_be_rolled_back(): void
    {
        $migration = require database_path(self::MIGRATION);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('events', 'type'));
        $this->assertFalse(Schema::hasColumn('events', 'location'));
        $this->assertFalse(Schema::hasColumn('events', 'attendances_recorded_at'));
        $this->assertTrue(Schema::hasColumn('events', 'date_start'));

        $migration->up();

        $this->assertTrue(Schema::hasColumns('events', ['type', 'location', 'attendances_recorded_at',]));
    }
}
