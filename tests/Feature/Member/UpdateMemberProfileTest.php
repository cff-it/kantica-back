<?php

namespace Tests\Feature\Member;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Firstname;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Voice;
use App\Models\VoiceGroup;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

class UpdateMemberProfileTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    private const array PAYLOAD = [
        'last_name'   => 'Razafy',
        'first_name'  => 'Hantaniaina',
        'voice_group' => 'B',
        'voice'       => 'B1',
        'phone'       => '032 98 765 43',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
    }

    public function test_a_secretary_updates_the_profile_of_a_member(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", self::PAYLOAD)
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.profile', [
                ...self::PAYLOAD,
                'contact_mail' => $member->mail,
                'completed'    => true,
            ])
            ->assertJsonPath('data.firstnames.0.first_name', 'Hantaniaina');

        $this->assertSame('Razafy', $member->refresh()->last_name);
        $this->assertDatabaseHas('firstnames', [
            'user_id'    => $member->id,
            'first_name' => 'Hantaniaina',
            'default'    => true,
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id'        => $member->id,
            'voice_group_id' => VoiceGroup::where('abbreviation', 'B')->value('id'),
            'voice_id'       => Voice::where('name', 'B1')->value('id'),
            'phone'          => '032 98 765 43',
        ]);
        $this->assertSame(1, UserProfile::where('user_id', $member->id)->count());
        $this->assertSame(1, Firstname::where('user_id', $member->id)->count());
    }

    public function test_an_admin_updates_the_profile_of_a_member(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->putJson("/api/members/{$member->id}", self::PAYLOAD)->assertOk();

        $this->assertSame('Razafy', $member->refresh()->last_name);
    }

    public function test_a_secretary_can_change_the_voice_of_a_validated_member(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", [...self::PAYLOAD, 'voice' => null,])
            ->assertOk()
            ->assertJsonPath('data.profile.voice_group', 'B')
            ->assertJsonPath('data.profile.voice', null);

        $this->assertDatabaseHas('user_profiles', [
            'user_id'        => $member->id,
            'voice_group_id' => VoiceGroup::where('abbreviation', 'B')->value('id'),
            'voice_id'       => null,
        ]);
    }

    public function test_the_profile_is_created_when_the_member_has_none_yet(): void
    {
        $member = $this->member('Rakoto', 'Jean', null, UserStatus::Pending);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", self::PAYLOAD)
            ->assertOk()
            ->assertJsonPath('data.profile.completed', true);

        $this->assertSame(1, UserProfile::where('user_id', $member->id)->count());
    }

    public function test_the_account_itself_is_left_untouched(): void
    {
        $member = $this->member('Rakoto', 'Jean', 'S', UserStatus::Pending);
        $mail = $member->mail;
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", [
            ...self::PAYLOAD,
            'mail'         => 'autre@exemple.com',
            'contact_mail' => 'autre@exemple.com',
            'status'       => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.profile.contact_mail', $mail);

        $member->refresh();
        $this->assertSame($mail, $member->mail);
        $this->assertSame(UserStatus::Pending, $member->status);
        $this->assertTrue($member->hasRole(RoleName::Member->value));
    }

    public function test_only_the_targeted_member_is_modified(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $other = $this->member('Andriamihaja', 'Feno', 'S');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", self::PAYLOAD)->assertOk();

        $this->assertSame('Andriamihaja', $other->refresh()->last_name);
        $this->assertDatabaseHas('firstnames', ['user_id' => $other->id, 'first_name' => 'Feno',]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id'        => $other->id,
            'voice_group_id' => VoiceGroup::where('abbreviation', 'S')->value('id'),
        ]);
    }

    public function test_the_member_reads_the_modified_data_on_their_own_profile(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->putJson("/api/members/{$member->id}", self::PAYLOAD)->assertOk();

        Sanctum::actingAs($member->refresh());

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('data.last_name', 'Razafy')
            ->assertJsonPath('data.voice_group', 'B')
            ->assertJsonPath('data.phone', '032 98 765 43');
    }

    public function test_the_same_validations_as_the_personal_profile_apply(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name', 'first_name', 'voice_group', 'phone',])
            ->assertJsonMissingValidationErrors(['voice', 'contact_mail',]);

        $this->putJson("/api/members/{$member->id}", [...self::PAYLOAD, 'voice_group' => 'X', 'voice' => null,])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice_group' => 'Le groupe de voix doit être S, A, T ou B.',]);

        $this->putJson("/api/members/{$member->id}", [...self::PAYLOAD, 'voice' => 'S1',])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice' => 'Cette voix n\'existe pas dans le groupe de voix choisi.',]);

        $this->putJson("/api/members/{$member->id}", [...self::PAYLOAD, 'phone' => str_repeat('0', 31),])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone',]);

        $this->assertSame('Rasoanaivo', $member->refresh()->last_name);
        $this->assertDatabaseHas('user_profiles', [
            'user_id'        => $member->id,
            'voice_group_id' => VoiceGroup::where('abbreviation', 'A')->value('id'),
        ]);
    }

    public function test_a_member_cannot_update_a_profile_through_this_endpoint(): void
    {
        $other = $this->member('Rasoanaivo', 'Hanta', 'A');
        $me = $this->member('Rabe', 'Soa', 'S');
        Sanctum::actingAs($me);

        $this->putJson("/api/members/{$other->id}", self::PAYLOAD)->assertForbidden();
        $this->putJson("/api/members/{$me->id}", self::PAYLOAD)->assertForbidden();

        $this->assertSame('Rasoanaivo', $other->refresh()->last_name);
        $this->assertSame('Rabe', $me->refresh()->last_name);
    }

    public function test_a_member_gets_a_403_even_for_an_unknown_identifier(): void
    {
        Sanctum::actingAs($this->member('Rabe', 'Soa', 'S'));

        $this->putJson('/api/members/999999', self::PAYLOAD)->assertForbidden();
    }

    public function test_an_unknown_member_returns_404_for_a_secretary(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson('/api/members/999999', self::PAYLOAD)->assertNotFound();
        $this->putJson('/api/members/abc', self::PAYLOAD)->assertNotFound();
    }

    public function test_a_guest_cannot_update_a_profile(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');

        $this->putJson("/api/members/{$member->id}", self::PAYLOAD)->assertUnauthorized();

        $this->assertSame('Rasoanaivo', $member->refresh()->last_name);
    }

    public function test_a_pending_secretary_cannot_update_a_profile(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($pending);

        $this->putJson("/api/members/{$member->id}", self::PAYLOAD)
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);

        $this->assertSame('Rasoanaivo', $member->refresh()->last_name);
    }
}
