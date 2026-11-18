<?php

namespace Tests\Feature\Member;

use App\Enums\DatePrecision;
use App\Enums\RoleName;
use App\Enums\SacramentType;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Firstname;
use App\Models\User;
use App\Models\UserEmergencyContact;
use App\Models\UserEngagement;
use App\Models\UserProfile;
use App\Models\UserSacrament;
use App\Models\Voice;
use App\Models\VoiceGroup;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Member\Concerns\BuildsMemberRecords;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

class UpdateMemberProfileTest extends TestCase
{
    use BuildsMemberRecords, CreatesMembers, RefreshDatabase;

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

        $this->putJson("/api/members/{$member->id}", $this->recordPayload())
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.profile', $this->expectedRecord($member->mail))
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

        $this->putJson("/api/members/{$member->id}", $this->recordPayload())->assertOk();

        $this->assertSame('Razafy', $member->refresh()->last_name);
    }

    public function test_each_word_of_the_first_name_is_stored_as_a_first_name_like_on_the_own_profile(): void
    {
        $member = $this->member('Rakotoarisoa', 'Hanta Rasoa', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", [...$this->recordPayload(), 'first_name' => 'Fanja  Marie Soa',])
            ->assertOk();

        // Les anciens prénoms sont remplacés, un mot = une ligne, le premier est le prénom par défaut, sans ligne vide
        $firstnames = Firstname::where('user_id', $member->id)->orderBy('order')->get();
        $this->assertSame(['Fanja', 'Marie', 'Soa',], $firstnames->pluck('first_name')->all());
        $this->assertSame([0, 1, 2,], $firstnames->pluck('order')->all());
        $this->assertSame([true, false, false,], $firstnames->pluck('default')->all());
    }

    public function test_a_secretary_can_change_the_voice_of_a_validated_member(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", [...$this->recordPayload(), 'voice' => null,])
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

        $this->putJson("/api/members/{$member->id}", $this->recordPayload())
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
            ...$this->recordPayload(),
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

        $this->putJson("/api/members/{$member->id}", $this->recordPayload())->assertOk();

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
        $this->putJson("/api/members/{$member->id}", $this->recordPayload())->assertOk();

        Sanctum::actingAs($member->refresh());

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('data.last_name', 'Razafy')
            ->assertJsonPath('data.voice_group', 'B')
            ->assertJsonPath('data.phone', '032 98 765 43');
    }

    public function test_the_identity_and_voice_validations_are_the_same_as_the_personal_profile(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name', 'first_name', 'voice_group', 'phone',])
            ->assertJsonMissingValidationErrors(['voice', 'contact_mail',]);

        $this->putJson("/api/members/{$member->id}", [...$this->recordPayload(), 'voice_group' => 'X', 'voice' => null,])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice_group' => 'Le groupe de voix doit être S, A, T ou B.',]);

        $this->putJson("/api/members/{$member->id}", [...$this->recordPayload(), 'voice' => 'S1',])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice' => 'Cette voix n\'existe pas dans le groupe de voix choisi.',]);

        $this->putJson("/api/members/{$member->id}", [...$this->recordPayload(), 'phone' => str_repeat('0', 31),])
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

        $this->putJson("/api/members/{$other->id}", $this->recordPayload())->assertForbidden();
        $this->putJson("/api/members/{$me->id}", $this->recordPayload())->assertForbidden();

        $this->assertSame('Rasoanaivo', $other->refresh()->last_name);
        $this->assertSame('Rabe', $me->refresh()->last_name);
    }

    public function test_a_member_gets_a_403_even_for_an_unknown_identifier(): void
    {
        Sanctum::actingAs($this->member('Rabe', 'Soa', 'S'));

        $this->putJson('/api/members/999999', $this->recordPayload())->assertForbidden();
    }

    public function test_an_unknown_member_returns_404_for_a_secretary(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson('/api/members/999999', $this->recordPayload())->assertNotFound();
        $this->putJson('/api/members/abc', $this->recordPayload())->assertNotFound();
    }

    public function test_a_guest_cannot_update_a_profile(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');

        $this->putJson("/api/members/{$member->id}", $this->recordPayload())->assertUnauthorized();

        $this->assertSame('Rasoanaivo', $member->refresh()->last_name);
    }

    public function test_a_pending_secretary_cannot_update_a_profile(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($pending);

        $this->putJson("/api/members/{$member->id}", $this->recordPayload())
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);

        $this->assertSame('Rasoanaivo', $member->refresh()->last_name);
    }

    public function test_the_whole_record_is_written_in_the_existing_tables_with_the_date_precisions(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", $this->recordPayload())->assertOk();

        $profile = UserProfile::where('user_id', $member->id)->firstOrFail();
        $this->assertSame('1998-03-12', $profile->birth_date->toDateString());
        $this->assertSame('Antananarivo', $profile->birth_place);
        $this->assertSame('Lot IVB 12 Faravohitra, Antananarivo', $profile->address);
        $this->assertSame('Hanta Rasoa', $profile->social_pseudo);
        $this->assertSame('1234 Faravohitra', $profile->region_registration_number);

        // Le jour et le mois inconnus valent « 1 » : la précision dit ce qui est réellement connu
        $engagement = UserEngagement::where('user_id', $member->id)->firstOrFail();
        $this->assertSame('2019-09-01', $engagement->adhesion_date->toDateString());
        $this->assertSame(DatePrecision::Month, $engagement->adhesion_date_precision);
        $this->assertSame('2020-12-08', $engagement->engagement_date->toDateString());
        $this->assertSame(DatePrecision::Day, $engagement->engagement_date_precision);
        $this->assertSame('Ecar Faravohitra', $engagement->engagement_place);

        $sacraments = UserSacrament::where('user_id', $member->id)->get()->keyBy(fn (UserSacrament $s) => $s->type->value);
        $this->assertSame(['baptism', 'confirmation', 'first_communion',], $sacraments->keys()->sort()->values()->all());
        $this->assertSame('1998-05-01', $sacraments['baptism']->date->toDateString());
        $this->assertSame(DatePrecision::Month, $sacraments['baptism']->date_precision);
        $this->assertSame('2008-01-01', $sacraments['first_communion']->date->toDateString());
        $this->assertSame(DatePrecision::Year, $sacraments['first_communion']->date_precision);
        $this->assertSame('2012-08-15', $sacraments['confirmation']->date->toDateString());
        $this->assertSame(DatePrecision::Day, $sacraments['confirmation']->date_precision);
        $this->assertSame('Cathédrale Andohalo', $sacraments['confirmation']->place);

        $contacts = UserEmergencyContact::where('user_id', $member->id)->get();
        $this->assertCount(1, $contacts);
        $this->assertSame('RASOANAIVO Papa, RASOANAIVO Maman', $contacts[0]->contact_name);
        $this->assertSame('034 00 000 02 / 033 00 000 03', $contacts[0]->contact_phone);
        $this->assertSame('Parents', $contacts[0]->relationship);
    }

    public function test_dates_are_accepted_in_every_documented_form(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", $this->recordPayload([
            'birth_date'      => '1998-03-12',
            'adhesion_date'   => '2019-09',
            'engagement_date' => '8 décembre 2020',
            // Une année seule peut arriver comme nombre JSON
            'baptism'         => ['date' => 1998, 'place' => 'Ecar Faravohitra',],
            'first_communion' => ['date' => '05.2008', 'place' => 'Ecar Faravohitra',],
            'confirmation'    => ['date' => '15 août 2012', 'place' => 'Cathédrale Andohalo',],
        ]))
            ->assertOk()
            ->assertJsonPath('data.profile.birth_date', '1998-03-12')
            ->assertJsonPath('data.profile.adhesion_date', '2019-09')
            ->assertJsonPath('data.profile.engagement_date', '2020-12-08')
            ->assertJsonPath('data.profile.baptism.date', '1998')
            ->assertJsonPath('data.profile.first_communion.date', '2008-05')
            ->assertJsonPath('data.profile.confirmation.date', '2012-08-15');
    }

    public function test_saving_twice_updates_the_same_rows(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->putJson("/api/members/{$member->id}", $this->recordPayload())->assertOk();

        $this->putJson("/api/members/{$member->id}", $this->recordPayload([
            'adhesion_date'     => '2018',
            'baptism'           => ['date' => '1998', 'place' => 'Ambohimanarina',],
            'emergency_contact' => ['relationships' => ['Famille', 'Ami(e) proche',], 'name' => 'Rabe Jean', 'phone' => '034 11 111 11',],
        ]))
            ->assertOk()
            ->assertJsonPath('data.profile.adhesion_date', '2018')
            ->assertJsonPath('data.profile.baptism', ['date' => '1998', 'place' => 'Ambohimanarina',])
            ->assertJsonPath('data.profile.emergency_contact.relationships', ['Famille', 'Ami(e) proche',]);

        $this->assertSame(1, UserEngagement::where('user_id', $member->id)->count());
        $this->assertSame(3, UserSacrament::where('user_id', $member->id)->count());
        $this->assertSame(1, UserEmergencyContact::where('user_id', $member->id)->count());
        $this->assertSame('Famille, Ami(e) proche', UserEmergencyContact::where('user_id', $member->id)->value('relationship'));
    }

    public function test_the_marriage_is_stored_then_removed_when_its_block_is_empty_or_missing(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $marriage = ['spouse' => 'Rabe Jean, membre de la CFF', 'date' => '2022', 'place' => 'Ecar Faravohitra',];
        $marriages = fn () => UserSacrament::where('user_id', $member->id)->where('type', SacramentType::Marriage);

        $this->putJson("/api/members/{$member->id}", $this->recordPayload(['marriage' => $marriage,]))
            ->assertOk()
            ->assertJsonPath('data.profile.marriage', $marriage);
        $this->assertSame(DatePrecision::Year, $marriages()->firstOrFail()->date_precision);

        // Bloc vide : plus de mariage
        $this->putJson("/api/members/{$member->id}", $this->recordPayload())
            ->assertOk()
            ->assertJsonPath('data.profile.marriage', ['spouse' => null, 'date' => null, 'place' => null,]);
        $this->assertSame(0, $marriages()->count());

        // Bloc absent : idem, et les trois autres sacrements restent
        $this->putJson("/api/members/{$member->id}", $this->recordPayload(['marriage' => $marriage,]))->assertOk();
        $this->putJson("/api/members/{$member->id}", Arr::except($this->recordPayload(), 'marriage'))->assertOk();
        $this->assertSame(0, $marriages()->count());
        $this->assertSame(3, UserSacrament::where('user_id', $member->id)->count());
    }

    public function test_updating_the_marriage_keeps_the_spouse_member_flag_that_is_not_on_the_form(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        UserSacrament::create([
            'user_id'          => $member->id,
            'type'             => SacramentType::Marriage,
            'spouse_name'      => 'Rabe Jean',
            'spouse_is_member' => true,
        ]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", $this->recordPayload([
            'marriage' => ['spouse' => 'Rabe Jean', 'date' => '12/06/2022', 'place' => 'Ecar Faravohitra',],
        ]))->assertOk();

        $marriage = UserSacrament::where('user_id', $member->id)->where('type', SacramentType::Marriage)->firstOrFail();
        $this->assertTrue($marriage->spouse_is_member);
        $this->assertSame('2022-06-12', $marriage->date->toDateString());
    }

    public function test_the_emergency_contact_is_a_single_row_whose_identity_is_kept(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $first = UserEmergencyContact::create([
            'user_id'       => $member->id,
            'contact_name'  => 'Ancien',
            'contact_phone' => '000',
            'relationship'  => 'Famille',
        ]);
        UserEmergencyContact::create(['user_id' => $member->id, 'contact_name' => 'Autre', 'contact_phone' => '111',]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", $this->recordPayload([
            'emergency_contact' => ['relationships' => ['Parents', 'Famille', 'Parents',], 'name' => 'Nouveau', 'phone' => '034 00 000 02',],
        ]))
            ->assertOk()
            ->assertJsonPath('data.profile.emergency_contact', [
                'relationships' => ['Parents', 'Famille',],
                'name'          => 'Nouveau',
                'phone'         => '034 00 000 02',
            ]);

        $contacts = UserEmergencyContact::where('user_id', $member->id)->get();
        $this->assertCount(1, $contacts);
        $this->assertSame($first->id, $contacts[0]->id);
        $this->assertSame($first->uuid, $contacts[0]->uuid);
        $this->assertSame('Parents, Famille', $contacts[0]->relationship);
    }

    public function test_optional_fields_can_be_left_out_and_are_then_cleared(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->putJson("/api/members/{$member->id}", $this->recordPayload())->assertOk();

        $minimal = Arr::except($this->recordPayload(), [
            'voice',
            'social_pseudo',
            'region_registration_number',
            'engagement_date',
            'engagement_place',
            'marriage',
        ]);

        $this->putJson("/api/members/{$member->id}", $minimal)
            ->assertOk()
            ->assertJsonPath('data.profile.social_pseudo', null)
            ->assertJsonPath('data.profile.region_registration_number', null)
            ->assertJsonPath('data.profile.engagement_date', null)
            ->assertJsonPath('data.profile.engagement_place', null)
            ->assertJsonPath('data.profile.adhesion_date', '2019-09');
    }

    public function test_the_precise_voice_is_kept_when_its_key_is_absent_and_the_group_is_unchanged(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $member->profile->update(['voice_id' => Voice::where('name', 'A2')->value('id'),]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        // Même groupe, sans clé `voice` : A2 reste
        $this->putJson("/api/members/{$member->id}", Arr::except($this->recordPayload(['voice_group' => 'A',]), 'voice'))
            ->assertOk()
            ->assertJsonPath('data.profile.voice_group', 'A')
            ->assertJsonPath('data.profile.voice', 'A2');

        // Autre groupe, sans clé `voice` : A2 ne ferait plus partie du groupe, elle est effacée
        $this->putJson("/api/members/{$member->id}", Arr::except($this->recordPayload(), 'voice'))
            ->assertOk()
            ->assertJsonPath('data.profile.voice_group', 'B')
            ->assertJsonPath('data.profile.voice', null);

        // Clé `voice` nulle : effacée même dans le même groupe
        $member->profile->update(['voice_id' => Voice::where('name', 'B1')->value('id'),]);
        $this->putJson("/api/members/{$member->id}", $this->recordPayload(['voice' => null,]))
            ->assertOk()
            ->assertJsonPath('data.profile.voice', null);
    }

    public function test_the_required_fields_of_the_record_are_validated(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'birth_date'        => 'La date de naissance est obligatoire.',
                'birth_place'       => 'Le lieu de naissance est obligatoire.',
                'address'           => 'L\'adresse est obligatoire.',
                'adhesion_date'     => 'La date d\'adhésion à la chorale est obligatoire.',
                'baptism'           => 'Le baptême est obligatoire.',
                'first_communion'   => 'La première communion est obligatoire.',
                'confirmation'      => 'La confirmation est obligatoire.',
                'emergency_contact' => 'Le contact en cas d\'urgence est obligatoire.',
            ])
            ->assertJsonMissingValidationErrors([
                'voice',
                'social_pseudo',
                'region_registration_number',
                'engagement_date',
                'engagement_place',
                'marriage',
            ]);

        $this->putJson("/api/members/{$member->id}", $this->recordPayload([
            'baptism'           => ['date' => null, 'place' => null,],
            'emergency_contact' => ['relationships' => [], 'name' => null, 'phone' => null,],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'baptism.date'                    => 'La date du baptême est obligatoire.',
                'baptism.place'                   => 'Le lieu du baptême est obligatoire.',
                'emergency_contact.relationships' => 'Le lien avec le contact en cas d\'urgence est obligatoire.',
                'emergency_contact.name'          => 'Le nom de la personne à contacter est obligatoire.',
                'emergency_contact.phone'         => 'Le numéro de téléphone à contacter est obligatoire.',
            ]);

        $this->assertSame('Rasoanaivo', $member->refresh()->last_name);
        $this->assertSame(0, UserSacrament::where('user_id', $member->id)->count());
    }

    public function test_the_dates_are_validated(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $nextYear = (string) (now()->year + 1);

        // La date de naissance est complète ; les autres dates peuvent être au mois ou à l'année, jamais dans le futur
        $invalid = [
            ['birth_date', '2008', 'La date de naissance n\'est pas une date valide (jj/mm/aaaa).',],
            ['birth_date', '05/1998', 'La date de naissance n\'est pas une date valide (jj/mm/aaaa).',],
            ['birth_date', '31/02/1998', 'La date de naissance n\'est pas une date valide (jj/mm/aaaa).',],
            ['birth_date', '01/01/'.$nextYear, 'La date de naissance ne peut pas être dans le futur.',],
            ['adhesion_date', 'bientôt', 'La date d\'adhésion à la chorale n\'est pas une date valide (jj/mm/aaaa, mm/aaaa ou aaaa).',],
            ['adhesion_date', '13/2019', 'La date d\'adhésion à la chorale n\'est pas une date valide (jj/mm/aaaa, mm/aaaa ou aaaa).',],
            ['adhesion_date', $nextYear, 'La date d\'adhésion à la chorale ne peut pas être dans le futur.',],
            ['engagement_date', '1850', 'La date d\'engagement n\'est pas une date valide (jj/mm/aaaa, mm/aaaa ou aaaa).',],
        ];

        foreach ($invalid as [$field, $value, $message]) {
            $this->putJson("/api/members/{$member->id}", $this->recordPayload([$field => $value,]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field => $message,]);
        }

        $this->putJson("/api/members/{$member->id}", $this->recordPayload([
            'baptism'         => ['date' => 'jamais', 'place' => 'Ecar Faravohitra',],
            'first_communion' => ['date' => $nextYear, 'place' => 'Ecar Faravohitra',],
            'marriage'        => ['spouse' => null, 'date' => '2/2/2/2', 'place' => null,],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['baptism.date', 'first_communion.date', 'marriage.date',]);

        $this->assertSame(0, UserSacrament::where('user_id', $member->id)->count());
    }

    public function test_the_emergency_contact_relationships_are_validated(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        // [clé en erreur, liens envoyés]
        $invalid = [
            ['emergency_contact.relationships', [],],
            ['emergency_contact.relationships.0', ['Parents, Famille',],],
            ['emergency_contact.relationships.0', [str_repeat('a', 51),],],
            // Joints par une virgule, les liens doivent tenir dans la colonne (100 caractères)
            ['emergency_contact.relationships', [str_repeat('a', 40), str_repeat('b', 40), str_repeat('c', 40),],],
        ];

        foreach ($invalid as [$errorKey, $relationships]) {
            $this->putJson("/api/members/{$member->id}", $this->recordPayload([
                'emergency_contact' => ['relationships' => $relationships, 'name' => 'Rabe', 'phone' => '034 00 000 02',],
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$errorKey,]);
        }
    }

    public function test_the_picture_cannot_be_changed_through_this_endpoint(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        $member->profile->update(['picture_url' => 'https://exemple.com/photo.jpg',]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->putJson("/api/members/{$member->id}", $this->recordPayload(['picture_url' => 'https://exemple.com/autre.jpg',]))
            ->assertOk()
            ->assertJsonPath('data.profile.picture_url', 'https://exemple.com/photo.jpg');
    }

    public function test_the_show_returns_what_the_update_wrote(): void
    {
        $member = $this->member('Rasoanaivo', 'Hanta', 'A');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->putJson("/api/members/{$member->id}", $this->recordPayload())->assertOk();

        $this->getJson("/api/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.profile', $this->expectedRecord($member->mail));
    }
}
