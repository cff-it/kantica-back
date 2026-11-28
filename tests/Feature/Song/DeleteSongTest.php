<?php

namespace Tests\Feature\Song;

use App\Enums\RoleName;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    // Voulu : le cantique est chargé avant la Policy, comme pour la modification (KAN-38). Un membre reçoit donc un 404
    // pour un cantique inconnu et un 403 pour un cantique existant, sans rien révéler : la liste est lisible par tous
    #[DataProvider('allRoles')]
    public function test_an_unknown_song_is_not_found_whatever_the_role(RoleName $role): void
    {
        Sanctum::actingAs($this->userWithRole($role));

        $this->deleteJson('/api/songs/999')->assertNotFound();
    }

    public static function allRoles(): array
    {
        return [
            'admin'      => [RoleName::Admin,],
            'membre'     => [RoleName::Member,],
            'secrétaire' => [RoleName::Secretary,],
        ];
    }

    // Relevé par la revue de KAN-18 : la requête charge le cantique pour la Policy, et DeleteSongAction le supprime sans
    // le relire
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
}
