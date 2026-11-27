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

// DELETE /api/songs/{song} : réservé à l'admin (DeleteSongRequest, SongPolicy::delete). Relevé par la revue de KAN-18 :
// la route n'avait aucun contrôle de rôle
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

    public function test_an_unknown_song_is_not_found(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->deleteJson('/api/songs/999')->assertNotFound();
    }

    public function test_a_guest_cannot_delete_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);

        $this->deleteJson("/api/songs/{$song->id}")->assertUnauthorized();

        $this->assertModelExists($song);
    }
}
