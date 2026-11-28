<?php

namespace Tests\Feature\Song;

use App\Enums\RoleName;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// DELETE /api/songs/{song} : réservé à l'admin (SongPolicy::delete, sur la route). Relevé par la revue de KAN-18 : la
// route n'avait aucun contrôle de rôle
class DeleteSongTest extends TestCase
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

    public function test_an_admin_deletes_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->deleteJson("/api/songs/{$song->id}")->assertNoContent();

        $this->assertModelMissing($song);
    }

    #[DataProvider('otherRoles')]
    public function test_only_an_admin_can_delete_a_song(RoleName $role): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);
        Sanctum::actingAs($this->userWithRole($role));

        $this->deleteJson("/api/songs/{$song->id}")->assertForbidden();

        $this->assertModelExists($song);
    }

    public static function otherRoles(): array
    {
        return [
            'membre'     => [RoleName::Member,],
            'secrétaire' => [RoleName::Secretary,],
        ];
    }

    // Le rôle est contrôlé sur la route, avec la classe : un autre rôle reçoit un 403 même pour un cantique inconnu,
    // comme pour la lecture, l'ajout et la modification ; l'admin, lui, reçoit le 404
    #[DataProvider('otherRoles')]
    public function test_the_role_is_checked_before_the_song_exists(RoleName $role): void
    {
        Sanctum::actingAs($this->userWithRole($role));

        $this->deleteJson('/api/songs/999')->assertForbidden();
    }

    public function test_an_unknown_song_is_not_found_for_an_admin(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->deleteJson('/api/songs/999')->assertNotFound();
    }

    // Relevé par la revue de KAN-18 : DeleteSongAction charge le cantique une seule fois avant de le supprimer
    public function test_the_song_is_read_once(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        DB::enableQueryLog();
        $this->deleteJson("/api/songs/{$song->id}")->assertNoContent();

        $reads = collect(DB::getQueryLog())->filter(
            fn (array $query) => str_starts_with($query['query'], 'select') && str_contains($query['query'], '"songs"'),
        );

        $this->assertCount(1, $reads);
        $this->assertModelMissing($song);
    }

    public function test_a_guest_cannot_delete_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);

        $this->deleteJson("/api/songs/{$song->id}")->assertUnauthorized();

        $this->assertModelExists($song);
    }

    public function test_a_pending_admin_cannot_delete_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($pending);

        $this->deleteJson("/api/songs/{$song->id}")
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);

        $this->assertModelExists($song);
    }
}
