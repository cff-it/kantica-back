<?php

namespace Tests\Feature\Song;

use App\Enums\RoleName;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SongReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// KAN-18 (US-04) : répertoire des cantiques, recherche par titre ou auteur, 20 cantiques par page (GET /api/songs)
class ListSongsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function actingAsMember(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Member));
    }

    private function song(string $title, ?string $author = null, ?string $reference = null): Song
    {
        return Song::create(['title' => $title, 'author' => $author, 'reference' => $reference,]);
    }

    public function test_a_member_lists_the_songs_sorted_by_title_without_case_or_accents(): void
    {
        $this->song('Salve Regina');
        $this->song('Ô filii et filiae');
        $this->song('adeste fideles');
        $this->song('Minuit, chrétiens');
        $this->song('Écoute la voix du Seigneur');
        $this->actingAsMember();

        $response = $this->getJson('/api/songs')->assertOk();

        $this->assertSame(
            ['adeste fideles', 'Écoute la voix du Seigneur', 'Minuit, chrétiens', 'Ô filii et filiae', 'Salve Regina',],
            $response->json('data.*.title'),
        );
    }

    public function test_a_row_carries_the_title_the_author_and_the_reference_without_the_sections(): void
    {
        $song = $this->song('Adeste fideles', 'John Francis Wade', '012');
        $this->actingAsMember();

        $this->getJson('/api/songs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $song->id)
            ->assertJsonPath('data.0.uuid', $song->uuid)
            ->assertJsonPath('data.0.title', 'Adeste fideles')
            ->assertJsonPath('data.0.author', 'John Francis Wade')
            ->assertJsonPath('data.0.reference', '012')
            ->assertJsonMissingPath('data.0.sections');
    }

    /**
     * @param  list<string>  $expectedTitles
     */
    #[DataProvider('searches')]
    public function test_the_search_looks_in_the_title_and_the_author_without_case_or_accents(
        string $search,
        array $expectedTitles,
    ): void {
        $this->song('Adeste fideles', 'John Francis Wade', '012');
        $this->song('Minuit, chrétiens', 'Adolphe Adam', '058');
        $this->song('Ô filii et filiae', 'Jean Tisserand', '071');
        $this->song('Kyrie XVII', 'Grégorien', '060');
        $this->actingAsMember();

        $response = $this->getJson('/api/songs?search='.urlencode($search))->assertOk();

        $this->assertSame($expectedTitles, $response->json('data.*.title'));
    }

    public static function searches(): array
    {
        return [
            'titre sans accent'    => ['chretiens', ['Minuit, chrétiens',],],
            'titre en majuscules'  => ['ADESTE', ['Adeste fideles',],],
            'titre accentué'       => ['ô filii', ['Ô filii et filiae',],],
            'fragment de titre'    => ['fidel', ['Adeste fideles',],],
            'auteur sans accent'   => ['gregorien', ['Kyrie XVII',],],
            'auteur complet'       => ['Adolphe Adam', ['Minuit, chrétiens',],],
            'titre puis auteur'    => ['adeste wade', ['Adeste fideles',],],
            'plusieurs résultats'  => ['ad', ['Adeste fideles', 'Minuit, chrétiens',],],
            'référence non cherchée' => ['012', [],],
            'aucun résultat'       => ['xyz', [],],
        ];
    }

    public function test_the_songs_count_ignores_the_search(): void
    {
        $this->song('Adeste fideles');
        $this->song('Salve Regina');
        $this->song('Kyrie XVII');
        $this->actingAsMember();

        $this->getJson('/api/songs?search=salve')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.songs_count', 3);
    }

    public function test_an_empty_repertoire_gives_an_empty_list(): void
    {
        $this->actingAsMember();

        $this->getJson('/api/songs')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.songs_count', 0);
    }

    public function test_an_empty_search_is_ignored(): void
    {
        $this->song('Adeste fideles');
        $this->song('Salve Regina');
        $this->actingAsMember();

        $this->getJson('/api/songs?search=')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/songs?search=%20%20')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_the_list_is_paginated_and_the_links_keep_the_search(): void
    {
        foreach (range(1, 25) as $number) {
            $this->song(sprintf('Cantique %02d', $number));
        }
        $this->song('Salve Regina');
        $this->actingAsMember();

        $this->getJson('/api/songs')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.title', 'Cantique 01')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.songs_count', 26);

        $second = $this->getJson('/api/songs?search=cantique&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.title', 'Cantique 21')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.songs_count', 26);

        $this->assertStringContainsString('search=cantique', $second->json('links.first'));
    }

    public function test_an_out_of_range_page_is_empty_and_page_zero_is_refused(): void
    {
        $this->song('Adeste fideles');
        $this->actingAsMember();

        $this->getJson('/api/songs?page=9')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/songs?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page' => 'La page doit être supérieure ou égale à 1.',]);
    }

    public function test_a_too_long_search_is_refused(): void
    {
        $this->actingAsMember();

        $this->getJson('/api/songs?search='.str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search' => 'La recherche ne doit pas dépasser 100 caractères.',]);
    }

    #[DataProvider('roles')]
    public function test_every_role_can_list_the_songs(RoleName $role): void
    {
        $this->song('Adeste fideles');
        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson('/api/songs')->assertOk()->assertJsonCount(1, 'data');
    }

    public static function roles(): array
    {
        return [
            'membre'     => [RoleName::Member,],
            'secrétaire' => [RoleName::Secretary,],
            'admin'      => [RoleName::Admin,],
        ];
    }

    public function test_an_account_without_role_cannot_list_the_songs(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/songs')->assertForbidden();
    }

    public function test_a_guest_cannot_list_the_songs(): void
    {
        $this->getJson('/api/songs')->assertUnauthorized();
    }

    public function test_a_pending_member_cannot_list_the_songs(): void
    {
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Member->value);
        Sanctum::actingAs($pending);

        $this->getJson('/api/songs')
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);
    }

    public function test_the_reference_songs_are_seeded_once_and_listed_in_alphabetical_order(): void
    {
        $this->seed(SongReferenceSeeder::class);
        $this->seed(SongReferenceSeeder::class);
        $this->actingAsMember();

        $response = $this->getJson('/api/songs')
            ->assertOk()
            ->assertJsonPath('meta.songs_count', 13)
            ->assertJsonPath('data.0.author', 'John Francis Wade')
            ->assertJsonPath('data.0.reference', '012');

        // « Ô filii » est rangé avec les O ; « Venez » passe avant « Veni » (ordre alphabétique strict, la maquette les inverse)
        $this->assertSame(
            [
                'Adeste fideles', 'Alma Redemptoris Mater', 'Ave Maria', 'Il est né le divin Enfant', 'Kyrie XVII',
                'Les anges dans nos campagnes', 'Minuit, chrétiens', 'Ô filii et filiae', 'Rorate caeli', 'Salve Regina',
                'Venez, divin Messie', 'Veni Creator Spiritus', 'Victimae paschali laudes',
            ],
            $response->json('data.*.title'),
        );
    }
}
