<?php

namespace Tests\Feature\User;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

/**
 * « Gestion des rôles » (KAN-35, US-25) : liste des comptes et changement de rôle, réservés à l'admin.
 */
class ManageRolesTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
        // Sans compte administrateur originel, sauf dans les tests qui le définissent
        config(['kantica.super_admin.mail' => null,]);
    }

    // ── Liste ───────────────────────────────────────────────────────────

    public function test_an_admin_lists_the_accounts_with_their_role_and_the_counts_per_role(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        $this->member('Rakotoarisoa', 'Lalaina', 'A', role: RoleName::Secretary);
        $this->member('Rabe', 'Mialy', 'S');
        $this->member('Rasoanaivo', 'Hanta', 'A', UserStatus::Pending);
        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.role_counts', ['admin' => 1, 'secretaire' => 1, 'membre' => 2,])
            ->assertJsonPath('data.0.last_name', 'Andriamanana')
            ->assertJsonPath('data.0.first_name', 'Feno')
            ->assertJsonPath('data.0.role', 'admin')
            ->assertJsonPath('data.0.mail', $admin->mail)
            ->assertJsonPath('data.1.last_name', 'Rabe')
            ->assertJsonPath('data.1.role', 'membre')
            ->assertJsonPath('data.2.last_name', 'Rakotoarisoa')
            ->assertJsonPath('data.2.role', 'secretaire')
            ->assertJsonPath('data.3.status', 'pending')
            ->assertJsonStructure(['data' => [['id', 'mail', 'last_name', 'first_name', 'status', 'role',],],]);
    }

    public function test_the_search_matches_names_and_emails_without_accents_and_keeps_the_counts(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        $elysee = $this->member('Élysée', 'Noël', 'S');
        $this->member('Rabe', 'Mialy', 'S');
        $elysee->update(['mail' => 'noel.elysee@exemple.com',]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/users?search=elysee')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $elysee->id)
            ->assertJsonPath('meta.role_counts.membre', 2);

        $this->getJson('/api/users?search=noel.elysee@exemple')->assertJsonPath('data.0.id', $elysee->id);
        $this->getJson('/api/users?search=mialy rabe')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/users?search=inconnu')->assertJsonPath('meta.total', 0);
    }

    public function test_the_list_is_paginated_by_twenty(): void
    {
        $admin = $this->member('Aaa', 'Admin', 'T', role: RoleName::Admin);

        foreach (range(1, 21) as $index) {
            $this->member('Membre'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'Test', 'S');
        }

        Sanctum::actingAs($admin);

        $this->getJson('/api/users')->assertJsonCount(20, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/users?page=2')->assertJsonCount(2, 'data');
    }

    public function test_the_original_admin_account_is_not_listed(): void
    {
        config(['kantica.super_admin.mail' => 'admin@kantica.test',]);
        $original = $this->userWithRole(RoleName::Admin);
        $original->update(['mail' => 'admin@kantica.test',]);
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.role_counts.admin', 1)
            ->assertJsonPath('data.0.id', $admin->id);
    }

    public function test_the_original_admin_account_is_hidden_whatever_the_case_of_its_email(): void
    {
        config(['kantica.super_admin.mail' => 'Admin@Kantica.test',]);
        $original = $this->userWithRole(RoleName::Admin);
        $original->update(['mail' => 'admin@kantica.test',]);
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.role_counts.admin', 1);

        // Protégé de la même façon au changement de rôle
        $this->putJson("/api/users/{$original->id}/role", ['role' => 'membre',])->assertUnprocessable();
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_accounts(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        $this->member('Rabe', 'Mialy', 'S');
        Sanctum::actingAs($admin);

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/users')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        // Premier appel à vide : il charge aussi le cache des permissions
        $count();
        $withTwo = $count();

        foreach (range(1, 15) as $index) {
            $this->member('Membre'.$index, 'Test', 'S', role: $index % 2 ? RoleName::Member : RoleName::Secretary);
        }

        $this->assertSame($withTwo, $count());
    }

    public function test_only_an_admin_can_list_the_accounts(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $this->getJson('/api/users')->assertForbidden();

        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->getJson('/api/users')->assertForbidden();
    }

    // ── Changement de rôle ──────────────────────────────────────────────

    public function test_an_admin_changes_the_role_of_an_account_which_keeps_a_single_role(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        $member = $this->member('Rabe', 'Mialy', 'S');
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$member->id}/role", ['role' => 'secretaire',])
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.first_name', 'Mialy')
            ->assertJsonPath('data.role', 'secretaire');

        $this->assertSame(['secretaire',], $member->refresh()->getRoleNames()->all());

        $this->putJson("/api/users/{$member->id}/role", ['role' => 'admin',])->assertJsonPath('data.role', 'admin');
        $this->assertSame(['admin',], $member->refresh()->getRoleNames()->all());
    }

    public function test_an_account_with_several_roles_keeps_only_the_chosen_one(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        $member = $this->member('Rabe', 'Mialy', 'S');
        $member->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$member->id}/role", ['role' => 'membre',])->assertJsonPath('data.role', 'membre');

        $this->assertSame(['membre',], $member->refresh()->getRoleNames()->all());
    }

    public function test_the_last_admin_cannot_lose_the_admin_role(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$admin->id}/role", ['role' => 'membre',])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Impossible de retirer le rôle Admin : c\'est le dernier administrateur.');

        $this->assertTrue($admin->refresh()->hasRole(RoleName::Admin->value));
    }

    public function test_an_admin_can_give_up_the_role_when_another_admin_remains(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        $other = $this->member('Rakoto', 'Jean', 'B', role: RoleName::Admin);
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$admin->id}/role", ['role' => 'secretaire',])
            ->assertOk()
            ->assertJsonPath('data.role', 'secretaire');

        // L'autre est maintenant le dernier admin
        Sanctum::actingAs($other);
        $this->putJson("/api/users/{$other->id}/role", ['role' => 'membre',])->assertUnprocessable();
    }

    public function test_keeping_the_admin_role_on_the_last_admin_is_accepted(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$admin->id}/role", ['role' => 'admin',])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_the_original_admin_account_keeps_its_role(): void
    {
        config(['kantica.super_admin.mail' => 'admin@kantica.test',]);
        $original = $this->userWithRole(RoleName::Admin);
        $original->update(['mail' => 'admin@kantica.test',]);
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$original->id}/role", ['role' => 'membre',])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Le compte administrateur originel de la chorale garde son rôle Admin.');

        $this->assertTrue($original->refresh()->hasRole(RoleName::Admin->value));

        // Il compte parmi les admins : l'autre admin peut donc quitter le rôle
        $this->putJson("/api/users/{$admin->id}/role", ['role' => 'membre',])->assertOk();
    }

    public function test_the_role_is_validated(): void
    {
        $admin = $this->member('Andriamanana', 'Feno', 'T', role: RoleName::Admin);
        $member = $this->member('Rabe', 'Mialy', 'S');
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$member->id}/role", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Le rôle est obligatoire.');

        foreach (['externe', 'Admin', 'responsable', ['admin',], 1,] as $role) {
            $this->putJson("/api/users/{$member->id}/role", ['role' => $role,])
                ->assertUnprocessable()
                ->assertJsonPath('errors.role.0', 'Le rôle doit être admin, secretaire ou membre.');
        }

        $this->assertSame(['membre',], $member->refresh()->getRoleNames()->all());
    }

    public function test_only_an_admin_can_change_a_role(): void
    {
        $member = $this->member('Rabe', 'Mialy', 'S');
        $secretary = $this->userWithRole(RoleName::Secretary);

        $this->putJson("/api/users/{$member->id}/role", ['role' => 'admin',])->assertUnauthorized();

        // Un membre ne peut pas se donner le rôle Admin (faille de l'ancien POST /api/users/{user}/roles)
        Sanctum::actingAs($member);
        $this->putJson("/api/users/{$member->id}/role", ['role' => 'admin',])->assertForbidden();
        $this->putJson('/api/users/999999/role', ['role' => 'admin',])->assertForbidden();

        Sanctum::actingAs($secretary);
        $this->putJson("/api/users/{$member->id}/role", ['role' => 'secretaire',])->assertForbidden();
        $this->putJson("/api/users/{$secretary->id}/role", ['role' => 'admin',])->assertForbidden();

        $this->assertSame(['membre',], $member->refresh()->getRoleNames()->all());
        $this->assertFalse($secretary->refresh()->hasRole(RoleName::Admin->value));
    }

    public function test_the_former_route_that_added_roles_no_longer_exists(): void
    {
        $member = $this->member('Rabe', 'Mialy', 'S');
        Sanctum::actingAs($member);

        $this->postJson("/api/users/{$member->id}/roles", ['role' => 'admin',])->assertNotFound();

        $this->assertFalse($member->refresh()->hasRole(RoleName::Admin->value));
    }

    public function test_an_unknown_account_returns_404_for_an_admin(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->putJson('/api/users/999999/role', ['role' => 'membre',])->assertNotFound();
        $this->putJson('/api/users/abc/role', ['role' => 'membre',])->assertNotFound();
    }

    public function test_a_pending_admin_cannot_manage_roles(): void
    {
        $member = $this->member('Rabe', 'Mialy', 'S');
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($pending);

        $this->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);
        $this->putJson("/api/users/{$member->id}/role", ['role' => 'admin',])->assertForbidden();
    }

    // ── Fiche membre ────────────────────────────────────────────────────

    public function test_the_member_record_shows_the_role_to_the_secretary_and_the_admin(): void
    {
        $member = $this->member('Rabe', 'Mialy', 'S', role: RoleName::Secretary);

        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->getJson("/api/members/{$member->id}")->assertOk()->assertJsonPath('data.roles', ['secretaire',]);

        Sanctum::actingAs($this->userWithRole(RoleName::Admin));
        $this->getJson("/api/members/{$member->id}")->assertOk()->assertJsonPath('data.roles', ['secretaire',]);
    }

    // ── Autres routes /api/users ────────────────────────────────────────

    public function test_the_other_user_routes_are_reserved_to_the_admin(): void
    {
        $member = $this->member('Rabe', 'Mialy', 'S');
        $payload = [
            'mail'                  => 'nouveau@exemple.com',
            'last_name'             => 'Nouveau',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ];

        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->getJson("/api/users/{$member->id}")->assertForbidden();
        $this->postJson('/api/users', $payload)->assertForbidden();

        Sanctum::actingAs($this->userWithRole(RoleName::Admin));
        $this->getJson("/api/users/{$member->id}")->assertOk()->assertJsonPath('data.id', $member->id);
        $this->getJson('/api/users/999999')->assertNotFound();
    }
}
