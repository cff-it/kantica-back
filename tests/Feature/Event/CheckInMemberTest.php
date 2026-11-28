<?php

namespace Tests\Feature\Event;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\EventCheck;
use App\Repositories\EventRepository;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_a_check_in_locks_the_event_inside_a_transaction_before_looking_for_a_duplicate(): void
    {
        $event = Event::create(['name' => 'Répétition', 'date_start' => '2026-10-24 17:00:00',]);
        $member = $this->member('Rasoanaivo', 'Hanta');
        $levels = new \ArrayObject();

        // Dépôt qui note le niveau de transaction au moment du verrou : SQLite sérialise déjà les écritures, la suite ne
        // verrait pas la disparition du verrou sans cela
        $this->app->instance(EventRepository::class, new class($levels) extends EventRepository
        {
            public function __construct(private \ArrayObject $levels) {}

            public function findForUpdate(int $id): Event
            {
                $this->levels->append(DB::transactionLevel());

                return parent::findForUpdate($id);
            }
        });
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $baseLevel = DB::transactionLevel();
        DB::enableQueryLog();

        $this->postJson("/api/events/{$event->id}/check-in", ['user_id' => $member->id,])->assertCreated();

        // Le verrou, le même que la saisie des présences, est pris dans une transaction ouverte par le pointage
        $this->assertSame([$baseLevel + 1,], $levels->getArrayCopy());

        // L'événement est lu avant les présences : le contrôle de doublon se fait sous le verrou
        $queries = collect(DB::getQueryLog())->pluck('query');
        $eventRead = $queries->search(fn (string $sql) => str_contains($sql, 'from "events"'));
        $checksRead = $queries->search(fn (string $sql) => str_contains($sql, 'from "event_checks"'));
        $this->assertNotFalse($eventRead);
        $this->assertNotFalse($checksRead);
        $this->assertLessThan($checksRead, $eventRead);

        // Sous PostgreSQL (CI), la requête de verrou est bien un SELECT ... FOR UPDATE sur l'événement
        if (DB::getDriverName() === 'pgsql') {
            $this->assertNotEmpty($queries->filter(
                fn (string $sql) => str_contains($sql, 'for update') && str_contains($sql, '"events"'),
            ));
        }
    }

    public function test_a_presence_written_between_the_check_and_the_write_gives_a_422_not_a_500(): void
    {
        $event = Event::create(['name' => 'Répétition', 'date_start' => '2026-10-24 17:00:00',]);
        $member = $this->member('Rasoanaivo', 'Hanta');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        // Une autre présence s'insère après le contrôle de doublon, juste avant l'écriture
        EventCheck::creating(function () use ($event, $member) {
            DB::table('event_checks')->insert([
                'event_id'   => $event->id,
                'user_id'    => $member->id,
                'date_in'    => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson("/api/events/{$event->id}/check-in", ['user_id' => $member->id,])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id',]);
    }
}
