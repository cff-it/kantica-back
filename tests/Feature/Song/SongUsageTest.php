<?php

namespace Tests\Feature\Song;

use App\Enums\RoleName;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// KAN-38 : historique d'usage d'un cantique (dernière fois chanté, occasion, nombre de fois) dans GET /api/songs/{song}
class SongUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($user);
    }

    private function song(string $title = 'Gloria'): Song
    {
        return Song::create(['title' => $title,]);
    }

    private function mass(string $date, ?string $occasion = null): int
    {
        return DB::table('masses')->insertGetId(['date' => $date, 'liturgical_name' => $occasion,]);
    }

    // Le cantique est chanté à cette partie de la messe (Entrée, Communion...)
    private function sing(Song $song, int $massId, string $part = 'Entrée'): void
    {
        $typeId = DB::table('mass_part_types')->where('name', $part)->value('id')
            ?? DB::table('mass_part_types')->insertGetId(['name' => $part,]);

        $partId = DB::table('mass_parts')->insertGetId([
            'uuid'              => (string) Str::uuid(),
            'mass_id'           => $massId,
            'mass_part_type_id' => $typeId,
        ]);

        DB::table('mass_songs')->insert(['mass_part_id' => $partId, 'song_id' => $song->id,]);
    }

    private function day(int $daysAgo): string
    {
        return today()->subDays($daysAgo)->toDateString();
    }

    public function test_a_song_never_sung_has_an_empty_usage(): void
    {
        $song = $this->song();

        $this->getJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJsonPath('data.usage', ['times_sung' => 0, 'last_sung' => null, 'history' => [],]);
    }

    public function test_the_last_time_a_song_was_sung_gives_the_date_the_occasion_and_the_part(): void
    {
        $song = $this->song();
        $recent = $this->mass($this->day(7), 'Christ Roi');
        $this->sing($song, $recent, 'Communion');

        $this->getJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJsonPath('data.usage.times_sung', 1)
            ->assertJsonPath('data.usage.last_sung', [
                'mass_id'  => $recent,
                'date'     => $this->day(7),
                'occasion' => 'Christ Roi',
                'part'     => 'Communion',
            ]);
    }

    public function test_the_history_goes_from_the_most_recent_mass_to_the_oldest(): void
    {
        $song = $this->song();
        // Créées dans le désordre : l'ordre de la réponse suit la date de la messe, pas celle de l'enregistrement
        $this->sing($song, $this->mass($this->day(30), 'Toussaint'), 'Entrée');
        $this->sing($song, $this->mass($this->day(7), 'Christ Roi'), 'Communion');
        $this->sing($song, $this->mass($this->day(14), 'Dimanche ordinaire'), 'Envoi');

        $response = $this->getJson("/api/songs/{$song->id}")->assertOk();

        $this->assertSame(3, $response->json('data.usage.times_sung'));
        $this->assertSame(
            [
                [$this->day(7), 'Christ Roi', 'Communion',],
                [$this->day(14), 'Dimanche ordinaire', 'Envoi',],
                [$this->day(30), 'Toussaint', 'Entrée',],
            ],
            collect($response->json('data.usage.history'))
                ->map(fn ($use) => [$use['date'], $use['occasion'], $use['part'],])
                ->all(),
        );
        $this->assertSame($response->json('data.usage.history.0'), $response->json('data.usage.last_sung'));
    }

    public function test_masses_of_the_same_day_are_listed_the_latest_created_first(): void
    {
        $song = $this->song();
        $morning = $this->mass($this->day(7), 'Messe du matin');
        $evening = $this->mass($this->day(7), 'Messe du soir');
        $this->sing($song, $morning);
        $this->sing($song, $evening);

        $this->getJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJsonPath('data.usage.last_sung.mass_id', $evening)
            ->assertJsonPath('data.usage.history.1.mass_id', $morning);
    }

    public function test_a_song_sung_twice_in_the_same_mass_is_counted_twice(): void
    {
        $song = $this->song();
        $mass = $this->mass($this->day(7), 'Christ Roi');
        $this->sing($song, $mass, 'Entrée');
        $this->sing($song, $mass, 'Envoi');

        $response = $this->getJson("/api/songs/{$song->id}")->assertOk();

        $this->assertSame(2, $response->json('data.usage.times_sung'));
        $this->assertSame(
            ['Entrée', 'Envoi',],
            collect($response->json('data.usage.history'))->pluck('part')->sort()->values()->all(),
        );
    }

    public function test_a_mass_without_a_liturgical_name_has_a_null_occasion(): void
    {
        $song = $this->song();
        $this->sing($song, $this->mass($this->day(7)));

        $this->getJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJsonPath('data.usage.last_sung.occasion', null);
    }

    public function test_a_mass_planned_for_later_is_not_counted_as_sung(): void
    {
        $song = $this->song();
        $this->sing($song, $this->mass($this->day(7), 'Christ Roi'));
        $this->sing($song, $this->mass(today()->addDays(7)->toDateString(), 'Avent 1'));

        $response = $this->getJson("/api/songs/{$song->id}")->assertOk();

        $this->assertSame(1, $response->json('data.usage.times_sung'));
        $this->assertSame('Christ Roi', $response->json('data.usage.last_sung.occasion'));
    }

    public function test_a_mass_of_today_is_counted(): void
    {
        $song = $this->song();
        $this->sing($song, $this->mass(today()->toDateString(), 'Messe du jour'));

        $this->getJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJsonPath('data.usage.times_sung', 1)
            ->assertJsonPath('data.usage.last_sung.occasion', 'Messe du jour');
    }

    public function test_the_usage_of_another_song_is_not_mixed_in(): void
    {
        $song = $this->song('Gloria');
        $other = $this->song('Sanctus');
        $this->sing($other, $this->mass($this->day(7), 'Christ Roi'));

        $this->getJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJsonPath('data.usage.times_sung', 0)
            ->assertJsonPath('data.usage.last_sung', null);
    }

    public function test_the_song_still_shows_its_sections_next_to_the_usage(): void
    {
        $song = $this->song();
        $song->sections()->create(['type' => 'couplet', 'lyrics' => 'Un', 'position' => 0,]);
        $this->sing($song, $this->mass($this->day(7), 'Christ Roi'));

        $this->getJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.usage.times_sung', 1);
    }

    public function test_the_song_list_does_not_load_the_usage(): void
    {
        $song = $this->song();
        $this->sing($song, $this->mass($this->day(7), 'Christ Roi'));

        $this->getJson('/api/songs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.usage');
    }
}
