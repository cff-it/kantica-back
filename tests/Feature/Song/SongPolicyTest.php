<?php

namespace Tests\Feature\Song;

use App\Enums\RoleName;
use App\Http\Requests\Song\CreateSongRequest;
use App\Http\Requests\Song\DeleteSongRequest;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// SongPolicy : les routes l'appellent avec la classe, les FormRequests de KAN-38 et la future fiche (KAN-25) avec le
// cantique. Les deux appels doivent donner la même réponse tant qu'aucun droit ne dépend du cantique
class SongPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(?RoleName $role): User
    {
        $user = User::factory()->create();

        if ($role !== null) {
            $user->assignRole($role->value);
        }

        return $user;
    }

    public function test_the_policy_gives_the_same_answer_with_or_without_a_song(): void
    {
        $song = Song::create(['title' => 'Adeste fideles',]);
        $admin = $this->userWithRole(RoleName::Admin);
        $member = $this->userWithRole(RoleName::Member);
        $withoutRole = $this->userWithRole(null);

        foreach ([$song, Song::class,] as $target) {
            $this->assertTrue($member->can('view', $target));
            $this->assertFalse($withoutRole->can('view', $target));

            foreach (['update', 'delete',] as $ability) {
                $this->assertTrue($admin->can($ability, $target));
                $this->assertFalse($member->can($ability, $target));
            }
        }
    }

    // Défense en profondeur : les FormRequests contrôlent aussi le rôle, indépendamment du `->can` des routes.
    // UpdateSongRequest (KAN-38) charge le cantique de la route pour la Policy : elle est couverte par HTTP
    // (SaveSongAuthorizationTest), pas ici
    public function test_the_form_requests_check_the_role_too(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $member = $this->userWithRole(RoleName::Member);

        foreach ([CreateSongRequest::class, DeleteSongRequest::class,] as $class) {
            foreach ([[$admin, true,], [$member, false,],] as [$user, $allowed]) {
                $request = new $class();
                $request->setUserResolver(fn () => $user);

                $this->assertSame($allowed, $request->authorize(), $class);
            }
        }
    }
}
