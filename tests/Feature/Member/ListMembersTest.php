<?php

namespace Tests\Feature\Member;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

class ListMembersTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    // La secrétaire qui consulte est elle-même une choriste : elle fait partie de la liste
    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);

        $this->secretary = $this->member('Ratsimba', 'Sarah', 'A', role: RoleName::Secretary);
    }

    public function test_a_secretary_lists_the_members_sorted_by_name(): void
    {
        $this->member('Zafy', 'Lova', 'T');
        $this->member('Rasoanaivo', 'Hanta', 'A');
        $this->member('Andriamihaja', 'Feno', 'S');
        Sanctum::actingAs($this->secretary);

        $response = $this->getJson('/api/members')->assertOk();

        $this->assertSame(
            ['Andriamihaja', 'Rasoanaivo', 'Ratsimba', 'Zafy',],
            $response->json('data.*.last_name'),
        );
    }

    public function test_a_row_carries_the_identity_the_voice_and_the_account_status(): void
    {
        $member = $this->member('Andriamihaja', 'Feno', 'S');
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonPath('data.0', [
                'id'          => $member->id,
                'last_name'   => 'Andriamihaja',
                'first_name'  => 'Feno',
                'voice_group' => 'S',
                'voice'       => null,
                'status'      => 'active',
            ]);
    }

    public function test_a_pending_account_is_listed_with_its_status_and_counted(): void
    {
        $this->member('Rakoto', 'Jean', 'B', UserStatus::Pending);
        Sanctum::actingAs($this->secretary);

        $response = $this->getJson('/api/members')->assertOk();

        $statuses = collect($response->json('data'))->pluck('status')->sort()->values()->all();
        $this->assertSame(['active', 'pending',], $statuses);
        $response->assertJsonPath('meta.registered_count', 2);
    }

    public function test_a_member_without_profile_is_listed_with_an_empty_voice_and_sorted_last(): void
    {
        $withoutProfile = $this->member('Aaa', 'Paul', null);
        $withoutName = User::factory()->create(['last_name' => null,]);
        Sanctum::actingAs($this->secretary);

        $response = $this->getJson('/api/members')->assertOk();

        $this->assertSame(
            [$withoutProfile->id, $this->secretary->id, $withoutName->id,],
            $response->json('data.*.id'),
        );
        $response->assertJsonPath('data.0.voice_group', null)->assertJsonPath('data.0.voice', null);
    }

    public function test_the_registered_count_and_the_voice_counts_cover_all_groups(): void
    {
        $this->member('Zafy', 'Lova', 'T');
        $this->member('Rabe', 'Soa', 'S');
        $this->member('Rado', 'Tiana', 'S');
        $this->member('Aaa', 'Paul', null);
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonPath('meta.registered_count', 5)
            ->assertJsonPath('meta.voice_counts', [
                ['voice_group' => 'S', 'name' => 'Soprano', 'count' => 2,],
                ['voice_group' => 'A', 'name' => 'Alto', 'count' => 1,],
                ['voice_group' => 'T', 'name' => 'Tenor', 'count' => 1,],
                ['voice_group' => 'B', 'name' => 'Bass', 'count' => 0,],
            ]);
    }

    public function test_the_voice_filter_keeps_only_that_group(): void
    {
        $this->member('Zafy', 'Lova', 'T');
        $soprano = $this->member('Rabe', 'Soa', 'S');
        $this->member('Aaa', 'Paul', null);
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/members?voice=S')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $soprano->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_the_counts_ignore_the_search_and_the_voice_filter(): void
    {
        $this->member('Zafy', 'Lova', 'T');
        $this->member('Rabe', 'Soa', 'S');
        Sanctum::actingAs($this->secretary);

        $response = $this->getJson('/api/members?voice=S&search=zafy')->assertOk();

        $response->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.registered_count', 3);
        $this->assertSame([1, 1, 1, 0,], collect($response->json('meta.voice_counts'))->pluck('count')->all());
    }

    #[DataProvider('searches')]
    public function test_the_search_ignores_case_and_accents_on_last_and_first_names(
        string $search,
        ?string $expectedLastName,
    ): void {
        $this->member('Élysée', 'Noël', 'S');
        $this->member('Rakotoarisoa', 'Hanta Rasoa', 'A');
        Sanctum::actingAs($this->secretary);

        $response = $this->getJson('/api/members?search='.urlencode($search))->assertOk();

        $this->assertSame(
            $expectedLastName === null ? [] : [$expectedLastName,],
            $response->json('data.*.last_name'),
        );
    }

    public static function searches(): array
    {
        return [
            'nom sans accent'      => ['elysee', 'Élysée',],
            'nom en majuscules'    => ['ÉLYSÉE', 'Élysée',],
            'prénom sans accent'   => ['noel', 'Élysée',],
            'prénom en majuscules' => ['HANTA', 'Rakotoarisoa',],
            'second prénom'        => ['rasoa', 'Rakotoarisoa',],
            'fragment de nom'      => ['akotoa', 'Rakotoarisoa',],
            'nom puis prénom'      => ['rakoto hanta', 'Rakotoarisoa',],
            'prénom puis nom'      => ['Hanta Rakotoarisoa', 'Rakotoarisoa',],
            'aucun résultat'       => ['xyz', null,],
        ];
    }

    public function test_an_empty_search_and_an_empty_voice_are_ignored(): void
    {
        $this->member('Zafy', 'Lova', 'T');
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/members?search=&voice=')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_invalid_voice_filter_is_refused(): void
    {
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/members?voice=X')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice' => 'Le groupe de voix doit être S, A, T ou B.',]);
    }

    public function test_the_list_is_paginated_and_the_links_keep_the_search(): void
    {
        foreach (range(1, 25) as $i) {
            $this->member("Nom{$i}", 'Prénom', 'S');
        }
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.registered_count', 26);

        $second = $this->getJson('/api/members?search=nom&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.registered_count', 26);

        $this->assertStringContainsString('search=nom', $second->json('links.first'));
    }

    public function test_an_out_of_range_page_is_empty_and_page_zero_is_refused(): void
    {
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/members?page=9')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/members?page=0')->assertUnprocessable()->assertJsonValidationErrors(['page',]);
    }

    public function test_the_original_admin_account_is_not_a_member(): void
    {
        config(['kantica.super_admin.mail' => 'root@kantica.test',]);
        $root = User::factory()->create(['mail' => 'root@kantica.test', 'last_name' => 'Root',]);
        $root->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.registered_count', 1);
    }

    public function test_an_admin_can_list_the_members(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->getJson('/api/members')->assertOk();
    }

    public function test_a_member_cannot_list_the_members(): void
    {
        Sanctum::actingAs($this->member('Rabe', 'Soa', 'S'));

        $this->getJson('/api/members')->assertForbidden();
    }

    public function test_a_guest_cannot_list_the_members(): void
    {
        $this->getJson('/api/members')->assertUnauthorized();
    }

    public function test_a_pending_secretary_cannot_list_the_members(): void
    {
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($pending);

        $this->getJson('/api/members')
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);
    }
}
