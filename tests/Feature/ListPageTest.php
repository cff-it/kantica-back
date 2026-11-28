<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Paramètre `page` des listes paginées (trait ValidatesPage) : mêmes bornes et mêmes messages partout. Relevé par la
// revue des correctifs de pagination : la borne était recopiée dans chaque FormRequest, et `page=abc` n'avait pas le même
// message selon la route. Cinq routes : les quatre listes et l'écran de choix de l'événement des présences (KAN-12), dont
// seul le groupe « Déjà saisis » (`data.recorded`) est paginé
class ListPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // L'admin a accès aux quatre listes
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
    }

    #[DataProvider('lists')]
    public function test_the_page_is_validated_the_same_way_on_every_list(string $url, string $listPath = 'data'): void
    {
        $this->getJson("{$url}?page=1")->assertOk();
        $this->getJson("{$url}?page=100000")->assertOk()->assertJsonCount(0, $listPath);

        $refused = [
            'abc'    => 'La page doit être un nombre entier.',
            '0'      => 'La page doit être supérieure ou égale à 1.',
            '100001' => 'La page demandée est trop grande.',
        ];

        foreach ($refused as $page => $message) {
            $this->getJson("{$url}?page={$page}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['page' => $message,]);
        }
    }

    /**
     * @return array<string, array{0: string, 1?: string}> route, et chemin JSON de la liste paginée si ce n'est pas `data`
     */
    public static function lists(): array
    {
        return [
            'membres'                      => ['/api/members',],
            'gestion des rôles'            => ['/api/users',],
            'historique des imports'       => ['/api/users/import',],
            'répertoire'                   => ['/api/songs',],
            'choix de l\'événement (saisis)' => ['/api/events/attendance-overview', 'data.recorded',],
        ];
    }
}
