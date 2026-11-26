<?php

namespace Tests\Feature\Member;

use App\Enums\DatePrecision;
use App\Enums\RoleName;
use App\Enums\SacramentType;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Models\UserEngagement;
use App\Models\UserProfile;
use App\Models\UserSacrament;
use App\Models\Voice;
use App\Models\VoiceGroup;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

class AssignVoiceTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
    }

    public function test_a_secretary_assigns_a_voice_to_a_member(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'T',])
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.profile.voice_group', 'T')
            ->assertJsonPath('data.firstnames.0.first_name', 'Hanta');

        $this->assertDatabaseHas('user_profiles', [
            'user_id'        => $member->id,
            'voice_group_id' => VoiceGroup::where('abbreviation', 'T')->value('id'),
        ]);
    }

    public function test_an_admin_assigns_a_voice_to_a_member(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'B',])
            ->assertOk()
            ->assertJsonPath('data.profile.voice_group', 'B');
    }

    public function test_the_voice_of_an_incomplete_record_can_be_changed_without_the_rest_of_the_record(): void
    {
        // La fiche de test n'a qu'un groupe de voix et un téléphone : le PUT complet répondrait 422
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'S',])->assertOk();

        $profile = $member->profile()->first();
        $this->assertSame(VoiceGroup::where('abbreviation', 'S')->value('id'), $profile->voice_group_id);
        $this->assertSame('034 12 345 67', $profile->phone);
        $this->assertSame(1, UserProfile::where('user_id', $member->id)->count());
    }

    public function test_the_precise_voice_is_cleared_when_the_group_changes(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $member->profile->update(['voice_id' => Voice::where('name', 'A2')->value('id'),]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'S',])
            ->assertOk()
            ->assertJsonPath('data.profile.voice_group', 'S')
            ->assertJsonPath('data.profile.voice', null);
    }

    public function test_the_precise_voice_is_kept_when_the_group_is_unchanged(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $member->profile->update(['voice_id' => Voice::where('name', 'A2')->value('id'),]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'A',])
            ->assertOk()
            ->assertJsonPath('data.profile.voice_group', 'A')
            ->assertJsonPath('data.profile.voice', 'A2');
    }

    public function test_the_rest_of_the_record_is_untouched_when_the_group_changes(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $member->profile->update([
            'voice_id'      => Voice::where('name', 'A2')->value('id'),
            'address'       => 'Lot II A 12 Faravohitra',
            'birth_place'   => 'Antananarivo',
            'social_pseudo' => 'hanta.r',
        ]);
        UserEngagement::create([
            'user_id'                 => $member->id,
            'adhesion_date'           => '2019-09-01',
            'adhesion_date_precision' => DatePrecision::Month,
            'engagement_place'        => 'Faravohitra',
        ]);
        UserSacrament::create([
            'user_id' => $member->id,
            'type'    => SacramentType::Baptism,
            'date'    => '2000-05-14',
            'place'   => 'Andohalo',
        ]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'B',])
            ->assertOk()
            ->assertJsonPath('data.profile.voice_group', 'B')
            ->assertJsonPath('data.profile.voice', null);

        $this->assertDatabaseHas('user_profiles', [
            'user_id'        => $member->id,
            'voice_group_id' => VoiceGroup::where('abbreviation', 'B')->value('id'),
            'voice_id'       => null,
            'phone'          => '034 12 345 67',
            'address'        => 'Lot II A 12 Faravohitra',
            'birth_place'    => 'Antananarivo',
            'social_pseudo'  => 'hanta.r',
        ]);
        $this->assertDatabaseHas('user_engagements', ['user_id' => $member->id, 'engagement_place' => 'Faravohitra',]);
        $this->assertDatabaseHas('user_sacraments', ['user_id' => $member->id, 'place' => 'Andohalo',]);
        $this->assertSame('Rasoanaivo', $member->refresh()->last_name);
    }

    public function test_nothing_is_written_when_the_group_is_unchanged(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $member->profile->update(['voice_id' => Voice::where('name', 'A2')->value('id'),]);
        $updatedAt = $member->profile->refresh()->updated_at;
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->travel(5)->minutes();

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'A',])->assertOk();

        $profile = $member->profile->refresh();
        $this->assertTrue($profile->updated_at->equalTo($updatedAt));
        $this->assertSame(Voice::where('name', 'A2')->value('id'), $profile->voice_id);
    }

    public function test_the_voice_of_a_pending_member_can_be_assigned(): void
    {
        $member = $this->member('Rakoto', 'Jean', 'A', UserStatus::Pending);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'B',])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.profile.voice_group', 'B');
    }

    public function test_a_member_without_a_record_gets_a_422_and_no_record_is_created(): void
    {
        $member = $this->member('Rakoto', 'Jean', null, UserStatus::Pending);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'B',])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('voice');

        $this->assertSame(0, UserProfile::where('user_id', $member->id)->count());
    }

    public function test_the_voice_is_validated(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.voice.0', 'La voix est obligatoire.');

        foreach (['X', 's', 'Soprano', 'A2', ['S',], ['voice' => 'S',], 1, true,] as $voice) {
            $this->putJson("/api/members/{$member->id}/voice", ['voice' => $voice,])
                ->assertUnprocessable()
                ->assertJsonPath('errors.voice.0', 'La voix doit être S, A, T ou B.');
        }

        $this->assertSame(
            VoiceGroup::where('abbreviation', 'A')->value('id'),
            $member->profile()->value('voice_group_id'),
        );
    }

    public function test_only_the_targeted_member_is_modified(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $other = $this->member('Rakoto', 'Jean', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'T',])->assertOk();

        $this->assertSame(VoiceGroup::where('abbreviation', 'A')->value('id'), $other->profile()->value('voice_group_id'));
    }

    public function test_a_member_cannot_assign_a_voice(): void
    {
        $me = $this->member('Rasoanaivo', 'Hanta', 'A');
        $other = $this->member('Rakoto', 'Jean', 'A');
        Sanctum::actingAs($me);

        $this->putJson("/api/members/{$other->id}/voice", ['voice' => 'T',])->assertForbidden();
        $this->putJson("/api/members/{$me->id}/voice", ['voice' => 'T',])->assertForbidden();
        $this->putJson('/api/members/999999/voice', ['voice' => 'T',])->assertForbidden();
    }

    public function test_an_unknown_member_returns_404_for_a_secretary(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson('/api/members/999999/voice', ['voice' => 'T',])->assertNotFound();
        $this->putJson('/api/members/abc/voice', ['voice' => 'T',])->assertNotFound();
    }

    public function test_a_guest_cannot_assign_a_voice(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'T',])->assertUnauthorized();
    }

    public function test_a_pending_secretary_cannot_assign_a_voice(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($pending);

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'T',])
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);
    }

    public function test_the_list_and_the_record_show_the_new_voice(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}/voice", ['voice' => 'T',])->assertOk();

        $this->getJson("/api/members/{$member->id}")->assertJsonPath('data.profile.voice_group', 'T');
        $this->getJson('/api/members?voice=T')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $member->id);
    }
}
