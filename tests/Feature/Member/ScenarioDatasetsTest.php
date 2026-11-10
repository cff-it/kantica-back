<?php

namespace Tests\Feature\Member;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Database\Seeders\MemberCountsSeeder;
use Database\Seeders\MemberPaginationSeeder;
use Database\Seeders\MemberReferenceSeeder;
use Database\Seeders\MemberScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Garde l'alignement entre les jeux de données (database/seeders/Member*Seeder.php) et les scénarios du plan de tests
 * (session-tracking/Lot-1/kan-33/test-plan.md) : chaque jeu est chargé puis interrogé comme le fait le plan.
 */
class ScenarioDatasetsTest extends TestCase
{
    use RefreshDatabase;

    // Ordre de la liste avec le jeu de référence : sans casse ni accents, le compte sans nom en dernier (L1, L6)
    private const array REFERENCE_ORDER = [
        'Aaa', 'Andriamihaja', 'Élysée', 'Rakoto', 'Rakotoarisoa', 'Randria', 'Randrianasolo', 'Rasoanaivo',
        'Ratsimba', 'Ravelo', 'Ravoavy', 'Zafy', null,
    ];

    private const array PAYLOAD = [
        'last_name'   => 'Razafy',
        'first_name'  => 'Hantaniaina',
        'voice_group' => 'B',
        'voice'       => 'B1',
        'phone'       => '032 98 765 43',
    ];

    public function test_the_reference_dataset_is_sorted_counted_and_exposes_the_statuses(): void
    {
        $this->seed(MemberReferenceSeeder::class);
        $this->signIn('secretaire');

        $response = $this->getJson('/api/members')->assertOk();

        $this->assertSame(self::REFERENCE_ORDER, $response->json('data.*.last_name'));
        // L3 : les comptes en attente sont listés et comptés
        $response->assertJsonPath('meta.registered_count', 13);
        $this->assertSame(
            ['Rakoto', 'Randrianasolo', 'Ravoavy',],
            collect($response->json('data'))->where('status', 'pending')->pluck('last_name')->all(),
        );
        // L4 : le membre sans fiche n'a pas de voix et n'est compté dans aucun groupe (3 + 3 + 1 + 2 = 9 sur 13)
        $response->assertJsonPath('data.0.last_name', 'Aaa')
            ->assertJsonPath('data.0.voice_group', null)
            ->assertJsonPath('data.0.voice', null);
        $this->assertSame([3, 3, 1, 2,], collect($response->json('meta.voice_counts'))->pluck('count')->all());
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<string|null>  $expected
     */
    #[DataProvider('searches')]
    public function test_the_reference_dataset_answers_each_search_as_the_plan_says(
        array $query,
        array $expected,
    ): void {
        $this->seed(MemberReferenceSeeder::class);
        $this->signIn('secretaire');

        $response = $this->getJson('/api/members?'.http_build_query($query))->assertOk();

        $this->assertSame($expected, $response->json('data.*.last_name'));
        $response->assertJsonPath('meta.total', count($expected))->assertJsonPath('meta.registered_count', 13);
    }

    public static function searches(): array
    {
        return [
            'S1 nom sans accent'       => [['search' => 'elysee',], ['Élysée',],],
            'S2 majuscules accentuées' => [['search' => 'ÉLYSÉE',], ['Élysée',],],
            'S3 prénom sans accent'    => [['search' => 'noel',], ['Élysée',],],
            'S4 prénom en majuscules'  => [['search' => 'HANTA',], ['Rakotoarisoa', 'Rasoanaivo',],],
            'S5 second prénom'         => [['search' => 'rasoa',], ['Rakotoarisoa', 'Rasoanaivo',],],
            'S6 fragment'              => [['search' => 'akotoa',], ['Rakotoarisoa',],],
            'S7 nom puis prénom'       => [['search' => 'rakoto hanta',], ['Rakotoarisoa',],],
            'S7 prénom puis nom'       => [['search' => 'Hanta Rakotoarisoa',], ['Rakotoarisoa',],],
            'S8 aucun résultat'        => [['search' => 'xyz',], [],],
            'S9 paramètres vides'      => [['search' => '', 'voice' => '',], self::REFERENCE_ORDER,],
            'F1 filtre soprano'        => [['voice' => 'S',], ['Andriamihaja', 'Élysée', 'Ravelo',],],
            'F3 filtre et recherche'   => [['voice' => 'S', 'search' => 'zafy',], [],],
        ];
    }

    public function test_the_reference_dataset_refuses_an_unknown_voice_filter(): void
    {
        $this->seed(MemberReferenceSeeder::class);
        $this->signIn('secretaire');

        $this->getJson('/api/members?voice=X')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice' => 'Le groupe de voix doit être S, A, T ou B.',]);
    }

    public function test_the_reference_accounts_cover_the_show_and_update_scenarios(): void
    {
        $this->seed(MemberReferenceSeeder::class);
        $this->signIn('secretaire');

        // V1 : fiche d'un membre actif
        $this->getJson('/api/members/'.$this->id('rasoanaivo.hanta'))
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.profile.last_name', 'Rasoanaivo')
            ->assertJsonPath('data.profile.first_name', 'Hanta')
            ->assertJsonPath('data.profile.voice_group', 'A')
            ->assertJsonPath('data.profile.voice', 'A2')
            ->assertJsonPath('data.profile.completed', true);

        // V3 : compte en attente sans fiche
        $this->getJson('/api/members/'.$this->id('attente.sans.fiche'))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.profile.completed', false)
            ->assertJsonPath('data.profile.voice_group', null)
            ->assertJsonPath('data.profile.phone', null);

        // M1 : modification d'un membre actif
        $this->putJson('/api/members/'.$this->id('rasoanaivo.hanta'), self::PAYLOAD)
            ->assertOk()
            ->assertJsonPath('data.profile.voice_group', 'B')
            ->assertJsonPath('data.profile.voice', 'B1')
            ->assertJsonPath('data.firstnames.0.first_name', 'Hantaniaina');

        // M4 : fiche créée pour le compte en attente sans fiche, qui reste en attente (M5)
        $this->putJson('/api/members/'.$this->id('attente.sans.fiche'), self::PAYLOAD)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.profile.completed', true);
    }

    public function test_the_reference_accounts_cover_the_access_scenarios(): void
    {
        $this->seed(MemberReferenceSeeder::class);
        $memberId = $this->id('rasoanaivo.hanta');

        // A1 : un membre est refusé, y compris pour son propre identifiant
        $this->signIn('membre');
        $this->getJson('/api/members')->assertForbidden();
        $this->getJson('/api/members/'.$memberId)->assertForbidden();
        $this->getJson('/api/members/'.$this->id('membre'))->assertForbidden();
        $this->putJson('/api/members/'.$memberId, self::PAYLOAD)->assertForbidden();

        // A5 : une secrétaire en attente de validation est refusée
        $this->signIn('secretaire.attente');
        $this->getJson('/api/members')
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);

        // V2, M2 : l'admin a les mêmes droits que la secrétaire
        $this->signIn('admin');
        $this->getJson('/api/members/'.$memberId)->assertOk();
        $this->putJson('/api/members/'.$memberId, self::PAYLOAD)->assertOk();
    }

    public function test_the_counts_dataset_gives_the_figures_of_c1_and_c2(): void
    {
        $this->seed(MemberCountsSeeder::class);
        $this->signIn('secretaire');

        $voiceCounts = [
            ['voice_group' => 'S', 'name' => 'Soprano', 'count' => 2,],
            ['voice_group' => 'A', 'name' => 'Alto', 'count' => 1,],
            ['voice_group' => 'T', 'name' => 'Tenor', 'count' => 1,],
            ['voice_group' => 'B', 'name' => 'Bass', 'count' => 0,],
        ];

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonPath('meta.registered_count', 5)
            ->assertJsonPath('meta.voice_counts', $voiceCounts);
        $this->getJson('/api/members?voice=S&search=zafy')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.registered_count', 5)
            ->assertJsonPath('meta.voice_counts', $voiceCounts);
    }

    public function test_the_pagination_dataset_gives_the_figures_of_p1_to_p4(): void
    {
        $this->seed(MemberPaginationSeeder::class);
        $this->signIn('secretaire');

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 26);
        $this->getJson('/api/members?page=2')->assertOk()->assertJsonCount(6, 'data');

        $filtered = $this->getJson('/api/members?search=nom&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.registered_count', 26);
        $this->assertStringContainsString('search=nom', $filtered->json('links.first'));

        $this->getJson('/api/members?page=9')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/members?page=0')->assertUnprocessable()->assertJsonValidationErrors(['page',]);
    }

    public function test_loading_a_dataset_replaces_the_previous_one_and_keeps_real_accounts(): void
    {
        User::factory()->create(['mail' => 'vraie.choriste@example.com',]);

        $this->seed(MemberPaginationSeeder::class);
        $this->seed(MemberCountsSeeder::class);
        $this->seed(MemberCountsSeeder::class);

        $this->assertSame(5, User::where('mail', 'like', '%@'.MemberScenarioSeeder::MAIL_DOMAIN)->count());
        $this->assertDatabaseHas('users', ['mail' => 'vraie.choriste@example.com',]);
        $this->assertDatabaseCount('firstnames', 5);
        $this->assertDatabaseCount('user_profiles', 4);
    }

    public function test_a_dataset_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectException(RuntimeException::class);

        app(MemberReferenceSeeder::class)->run();
    }

    private function signIn(string $account): void
    {
        Sanctum::actingAs(User::where('mail', $account.'@'.MemberScenarioSeeder::MAIL_DOMAIN)->firstOrFail());
    }

    private function id(string $account): int
    {
        return User::where('mail', $account.'@'.MemberScenarioSeeder::MAIL_DOMAIN)->firstOrFail()->id;
    }
}
