<?php

namespace Tests\Feature\Member;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Models\Voice;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

class ShowMemberTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
    }

    public function test_a_secretary_reads_the_profile_of_any_member(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $member->update(['mail' => 'hanta@exemple.com',]);
        $member->profile->update(['voice_id' => Voice::where('name', 'A2')->value('id'),]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->getJson("/api/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.mail', 'hanta@exemple.com')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.profile', [
                'last_name'    => 'Rasoanaivo',
                'first_name'   => 'Hanta',
                'voice_group'  => 'A',
                'voice'        => 'A2',
                'phone'        => '034 12 345 67',
                'contact_mail' => 'hanta@exemple.com',
                'completed'    => true,
            ])
            ->assertJsonMissingPath('data.password');
    }

    public function test_an_admin_reads_the_profile_of_a_member(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->getJson("/api/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.profile.last_name', 'Rasoanaivo');
    }

    public function test_a_pending_member_without_profile_is_readable_with_its_status(): void
    {
        $member = $this->member('Rakoto', 'Jean', null, UserStatus::Pending);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->getJson("/api/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.profile.completed', false)
            ->assertJsonPath('data.profile.voice_group', null)
            ->assertJsonPath('data.profile.phone', null);
    }

    public function test_a_member_cannot_read_a_profile_through_this_endpoint(): void
    {
        $other = $this->member('Rasoanaivo', 'Hanta', 'A');
        $me = $this->member('Rabe', 'Soa', 'S');
        Sanctum::actingAs($me);

        $this->getJson("/api/members/{$other->id}")->assertForbidden();
        $this->getJson("/api/members/{$me->id}")->assertForbidden();
    }

    public function test_a_member_gets_a_403_even_for_an_unknown_identifier(): void
    {
        Sanctum::actingAs($this->member('Rabe', 'Soa', 'S'));

        $this->getJson('/api/members/999999')->assertForbidden();
    }

    public function test_an_unknown_member_returns_404_for_a_secretary(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->getJson('/api/members/999999')->assertNotFound();
        $this->getJson('/api/members/abc')->assertNotFound();
    }

    public function test_a_guest_cannot_read_a_profile(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');

        $this->getJson("/api/members/{$member->id}")->assertUnauthorized();
    }

    public function test_a_pending_secretary_cannot_read_a_profile(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($pending);

        $this->getJson("/api/members/{$member->id}")
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);
    }
}
