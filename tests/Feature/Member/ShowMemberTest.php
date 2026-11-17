<?php

namespace Tests\Feature\Member;

use App\Enums\DatePrecision;
use App\Enums\RoleName;
use App\Enums\SacramentType;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Models\UserEmergencyContact;
use App\Models\UserEngagement;
use App\Models\UserSacrament;
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
                // Aucun des autres champs de la fiche n'est renseigné : les blocs gardent leur forme, aux champs nuls
                'birth_date'                 => null,
                'birth_place'                => null,
                'address'                    => null,
                'social_pseudo'              => null,
                'region_registration_number' => null,
                'picture_url'                => null,
                'adhesion_date'              => null,
                'engagement_date'            => null,
                'engagement_place'           => null,
                'baptism'                    => ['date' => null, 'place' => null,],
                'first_communion'            => ['date' => null, 'place' => null,],
                'confirmation'               => ['date' => null, 'place' => null,],
                'marriage'                   => ['spouse' => null, 'date' => null, 'place' => null,],
                'emergency_contact'          => ['relationships' => [], 'name' => null, 'phone' => null,],
            ])
            ->assertJsonMissingPath('data.password');
    }

    public function test_a_secretary_reads_the_full_record_with_the_partial_dates_and_the_relationships(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $member->profile->update([
            'birth_date'                 => '1998-03-12',
            'birth_place'                => 'Antananarivo',
            'address'                    => 'Lot IVB 12 Faravohitra',
            'social_pseudo'              => 'Hanta Rasoa',
            'region_registration_number' => '1234 Faravohitra',
            'picture_url'                => 'https://exemple.com/hanta.jpg',
        ]);
        UserEngagement::create([
            'user_id'                   => $member->id,
            'adhesion_date'             => '2019-09-01',
            'adhesion_date_precision'   => DatePrecision::Month,
            'engagement_date'           => '2020-12-08',
            'engagement_date_precision' => DatePrecision::Day,
            'engagement_place'          => 'Ecar Faravohitra',
        ]);
        UserSacrament::create([
            'user_id'        => $member->id,
            'type'           => SacramentType::Baptism,
            'date'           => '1998-05-01',
            'date_precision' => DatePrecision::Month,
            'place'          => 'Ecar Faravohitra',
        ]);
        UserSacrament::create([
            'user_id'        => $member->id,
            'type'           => SacramentType::FirstCommunion,
            'date'           => '2008-01-01',
            'date_precision' => DatePrecision::Year,
            'place'          => 'Ecar Faravohitra',
        ]);
        // Ligne sans précision (importée ou antérieure à la migration) : date complète
        UserSacrament::create([
            'user_id' => $member->id,
            'type'    => SacramentType::Confirmation,
            'date'    => '2012-08-15',
            'place'   => 'Cathédrale Andohalo',
        ]);
        UserSacrament::create([
            'user_id'     => $member->id,
            'type'        => SacramentType::Marriage,
            'spouse_name' => 'Rabe Jean',
            'date'        => '2022-06-12',
            'place'       => 'Ecar Faravohitra',
        ]);
        // Liens joints par une virgule, comme l'import les écrit
        UserEmergencyContact::create([
            'user_id'       => $member->id,
            'contact_name'  => 'RASOANAIVO Papa',
            'contact_phone' => '034 00 000 02',
            'relationship'  => 'Parents,  Famille ,',
        ]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->getJson("/api/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.profile.birth_date', '1998-03-12')
            ->assertJsonPath('data.profile.birth_place', 'Antananarivo')
            ->assertJsonPath('data.profile.address', 'Lot IVB 12 Faravohitra')
            ->assertJsonPath('data.profile.social_pseudo', 'Hanta Rasoa')
            ->assertJsonPath('data.profile.region_registration_number', '1234 Faravohitra')
            ->assertJsonPath('data.profile.picture_url', 'https://exemple.com/hanta.jpg')
            ->assertJsonPath('data.profile.adhesion_date', '2019-09')
            ->assertJsonPath('data.profile.engagement_date', '2020-12-08')
            ->assertJsonPath('data.profile.engagement_place', 'Ecar Faravohitra')
            ->assertJsonPath('data.profile.baptism', ['date' => '1998-05', 'place' => 'Ecar Faravohitra',])
            ->assertJsonPath('data.profile.first_communion', ['date' => '2008', 'place' => 'Ecar Faravohitra',])
            ->assertJsonPath('data.profile.confirmation', ['date' => '2012-08-15', 'place' => 'Cathédrale Andohalo',])
            ->assertJsonPath('data.profile.marriage', ['spouse' => 'Rabe Jean', 'date' => '2022-06-12', 'place' => 'Ecar Faravohitra',])
            ->assertJsonPath('data.profile.emergency_contact', [
                'relationships' => ['Parents', 'Famille',],
                'name'          => 'RASOANAIVO Papa',
                'phone'         => '034 00 000 02',
            ]);
    }

    public function test_the_own_profile_keeps_the_short_format(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($member);

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonMissingPath('data.birth_date')
            ->assertJsonMissingPath('data.baptism')
            ->assertJsonMissingPath('data.emergency_contact');
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
