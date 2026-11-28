<?php

namespace Tests\Feature\Song;

use App\Enums\RoleName;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// POST /api/songs et PUT /api/songs/{song} : réservés à l'admin (SongPolicy::create et update, sur la route). Relevé
// par la revue de KAN-18 : tant que les FormRequests de KAN-38 ne sont pas fusionnées, tout compte validé, même sans
// rôle, pouvait créer ou modifier un cantique
class SaveSongAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function actingAsRole(?RoleName $role): void
    {
        $user = User::factory()->create();

        if ($role !== null) {
            $user->assignRole($role->value);
        }

        Sanctum::actingAs($user);
    }

    public function test_an_admin_creates_and_updates_a_song(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $id = $this->postJson('/api/songs', ['title' => 'Adeste fideles',])
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/songs/{$id}", ['title' => 'Adeste fideles (refrain)',])
            ->assertOk()
            ->assertJsonPath('data.title', 'Adeste fideles (refrain)');
    }

    #[DataProvider('otherAccounts')]
    public function test_only_an_admin_can_create_a_song(?RoleName $role): void
    {
        $this->actingAsRole($role);

        $this->postJson('/api/songs', ['title' => 'Adeste fideles',])->assertForbidden();

        $this->assertDatabaseCount('songs', 0);
    }

    // Le 403 passe avant le 404 : contrôle fait avec la classe, sans charger le cantique
    #[DataProvider('otherAccounts')]
    public function test_only_an_admin_can_update_a_song(?RoleName $role): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);
        $this->actingAsRole($role);

        $this->putJson("/api/songs/{$song->id}", ['title' => 'Modifié',])->assertForbidden();
        $this->putJson('/api/songs/999', ['title' => 'Modifié',])->assertForbidden();

        $this->assertSame('Adeste fideles', $song->fresh()->title);
    }

    public static function otherAccounts(): array
    {
        return [
            'secrétaire' => [RoleName::Secretary,],
            'membre'     => [RoleName::Member,],
            'sans rôle'  => [null,],
        ];
    }

    public function test_an_admin_gets_404_for_an_unknown_song(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $this->putJson('/api/songs/999', ['title' => 'Modifié',])->assertNotFound();
        $this->putJson('/api/songs/abc', ['title' => 'Modifié',])->assertNotFound();
    }

    public function test_a_guest_cannot_create_nor_update_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);

        $this->postJson('/api/songs', ['title' => 'Nouveau',])->assertUnauthorized();
        $this->putJson("/api/songs/{$song->id}", ['title' => 'Modifié',])->assertUnauthorized();

        $this->assertDatabaseCount('songs', 1);
        $this->assertSame('Adeste fideles', $song->fresh()->title);
    }

    // Le compte doit aussi être validé : un admin en attente est refusé par le middleware `account.active`
    public function test_a_pending_admin_cannot_create_nor_update_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($pending);

        $this->postJson('/api/songs', ['title' => 'Nouveau',])
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);
        $this->putJson("/api/songs/{$song->id}", ['title' => 'Modifié',])
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);

        $this->assertDatabaseCount('songs', 1);
        $this->assertSame('Adeste fideles', $song->fresh()->title);
    }
}
