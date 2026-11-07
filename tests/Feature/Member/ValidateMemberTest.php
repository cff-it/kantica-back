<?php

namespace Tests\Feature\Member;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Notifications\AccountValidatedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidateMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    public function test_a_secretary_can_validate_a_pending_member(): void
    {
        $member = $this->pendingMember();
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson("/api/members/{$member->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', UserStatus::Active->value);

        $this->assertSame(UserStatus::Active, $member->refresh()->status);
        Notification::assertSentTo($member, AccountValidatedNotification::class);
    }

    public function test_an_admin_can_validate_a_pending_member(): void
    {
        $member = $this->pendingMember();
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->postJson("/api/members/{$member->id}/validate")->assertOk();

        $this->assertSame(UserStatus::Active, $member->refresh()->status);
    }

    public function test_a_member_cannot_validate_an_account(): void
    {
        $member = $this->pendingMember();
        Sanctum::actingAs($this->userWithRole(RoleName::Member));

        $this->postJson("/api/members/{$member->id}/validate")->assertForbidden();

        $this->assertSame(UserStatus::Pending, $member->refresh()->status);
        Notification::assertNothingSent();
    }

    public function test_validating_an_active_account_has_no_effect(): void
    {
        $member = User::factory()->create();
        $member->assignRole(RoleName::Member->value);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson("/api/members/{$member->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', UserStatus::Active->value);

        Notification::assertNothingSent();
    }

    public function test_validating_an_unknown_member_returns_404(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members/999999/validate')->assertNotFound();
    }

    public function test_a_guest_cannot_validate_an_account(): void
    {
        $member = $this->pendingMember();

        $this->postJson("/api/members/{$member->id}/validate")->assertUnauthorized();
    }

    public function test_a_pending_secretary_cannot_validate_an_account(): void
    {
        $member = $this->pendingMember();
        $secretary = User::factory()->pending()->create();
        $secretary->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($secretary);

        $this->postJson("/api/members/{$member->id}/validate")
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);

        $this->assertSame(UserStatus::Pending, $member->refresh()->status);
    }

    public function test_the_validated_member_gets_access_to_business_routes(): void
    {
        $member = $this->pendingMember();

        Sanctum::actingAs($member);
        $this->getJson('/api/me')->assertOk();
        $this->postJson("/api/members/{$member->id}/validate")->assertForbidden();

        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->postJson("/api/members/{$member->id}/validate")->assertOk();

        Sanctum::actingAs($member->refresh());
        $this->postJson("/api/members/{$member->id}/validate")
            ->assertForbidden()
            ->assertJsonMissingPath('code');
    }

    private function pendingMember(): User
    {
        $member = User::factory()->pending()->create();
        $member->assignRole(RoleName::Member->value);

        return $member;
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
