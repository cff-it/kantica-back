<?php

namespace Tests\Feature\Song;

use App\Enums\RoleName;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// GET /api/songs/{song} : même droit que la liste du répertoire (SongPolicy::viewAny). Relevé par la revue de KAN-18 :
// un compte sans rôle, refusé sur la liste, pouvait lire chaque cantique par son identifiant
class ShowSongTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    #[DataProvider('roles')]
    public function test_every_role_reads_a_song(RoleName $role): void
    {
        $song = Song::create(['title' => 'Adeste fideles', 'author' => 'John Francis Wade',]);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        Sanctum::actingAs($user);

        $this->getJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Adeste fideles');
    }

    public static function roles(): array
    {
        return [
            'admin'      => [RoleName::Admin,],
            'secrétaire' => [RoleName::Secretary,],
            'membre'     => [RoleName::Member,],
        ];
    }

    // Le 403 passe avant le 404 : un compte sans rôle n'apprend rien sur les identifiants existants
    public function test_an_account_without_role_cannot_read_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/songs/{$song->id}")->assertForbidden();
        $this->getJson('/api/songs/999')->assertForbidden();
    }

    public function test_an_unknown_song_is_not_found(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Member->value);
        Sanctum::actingAs($user);

        $this->getJson('/api/songs/999')->assertNotFound();
    }

    public function test_a_guest_cannot_read_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);

        $this->getJson("/api/songs/{$song->id}")->assertUnauthorized();
    }
}
