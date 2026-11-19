<?php

namespace Tests\Feature\Member;

use App\Enums\DatePrecision;
use App\Enums\RoleName;
use App\Enums\SacramentType;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Mail\MemberInvitationMail;
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
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Member\Concerns\BuildsMemberRecords;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

class CreateMemberTest extends TestCase
{
    use BuildsMemberRecords, CreatesMembers, RefreshDatabase;

    private const string MAIL = 'hanta.razafy@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VoiceSeeder::class);
        Mail::fake();
    }

    /**
     * Payload valide de POST /api/members : l'email du compte et la même fiche complète que PUT /api/members/{id}.
     *
     * @param  array<string, mixed>  $overrides  remplace des clés de premier niveau (un bloc se remplace en entier)
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $this->recordPayload(['mail' => self::MAIL, ...$overrides,]);
    }

    private function createdMember(string $mail = self::MAIL): User
    {
        return User::where('mail', $mail)->firstOrFail();
    }

    private function sentInvitation(): MemberInvitationMail
    {
        return Mail::queued(MemberInvitationMail::class)->firstOrFail();
    }

    public function test_a_secretary_creates_a_member_with_the_whole_record(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.mail', self::MAIL)
            ->assertJsonPath('data.last_name', 'Razafy')
            ->assertJsonPath('data.status', UserStatus::Active->value)
            ->assertJsonPath('data.firstnames.0.first_name', 'Hantaniaina')
            ->assertJsonPath('data.profile', $this->expectedRecord(self::MAIL));

        $member = $this->createdMember();

        $this->assertSame('Razafy', $member->last_name);
        $this->assertDatabaseHas('firstnames', [
            'user_id'    => $member->id,
            'first_name' => 'Hantaniaina',
            'default'    => true,
        ]);
        $this->assertSame(1, UserProfile::where('user_id', $member->id)->count());
    }

    public function test_an_admin_creates_a_member(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->postJson('/api/members', $this->payload())->assertCreated();

        $this->assertSame('Razafy', $this->createdMember()->last_name);
    }

    public function test_the_whole_record_is_written_in_the_existing_tables_with_the_date_precisions(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload())->assertCreated();

        $member = $this->createdMember();

        $profile = UserProfile::where('user_id', $member->id)->firstOrFail();
        $this->assertSame(VoiceGroup::where('abbreviation', 'B')->value('id'), $profile->voice_group_id);
        $this->assertSame(Voice::where('name', 'B1')->value('id'), $profile->voice_id);
        $this->assertSame('032 98 765 43', $profile->phone);
        $this->assertSame('1998-03-12', $profile->birth_date->toDateString());
        $this->assertSame('Antananarivo', $profile->birth_place);
        $this->assertSame('Lot IVB 12 Faravohitra, Antananarivo', $profile->address);
        $this->assertSame('Hanta Rasoa', $profile->social_pseudo);
        $this->assertSame('1234 Faravohitra', $profile->region_registration_number);
        $this->assertNull($profile->picture_url);

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

    public function test_the_marriage_is_stored_when_given_and_left_out_otherwise(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $marriage = ['spouse' => 'Rabe Jean, membre de la CFF', 'date' => '2022', 'place' => 'Ecar Faravohitra',];

        $this->postJson('/api/members', $this->payload(['marriage' => $marriage,]))
            ->assertCreated()
            ->assertJsonPath('data.profile.marriage', $marriage);

        $married = UserSacrament::where('user_id', $this->createdMember()->id)->where('type', SacramentType::Marriage)->firstOrFail();
        $this->assertSame(DatePrecision::Year, $married->date_precision);

        $this->postJson('/api/members', Arr::except($this->payload(['mail' => 'celibataire@example.com',]), 'marriage'))->assertCreated();

        $single = $this->createdMember('celibataire@example.com');
        $this->assertSame(0, UserSacrament::where('user_id', $single->id)->where('type', SacramentType::Marriage)->count());
        $this->assertSame(3, UserSacrament::where('user_id', $single->id)->count());
    }

    public function test_the_optional_fields_can_be_left_out(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $minimal = Arr::except($this->payload(), [
            'voice',
            'social_pseudo',
            'region_registration_number',
            'engagement_date',
            'engagement_place',
            'marriage',
        ]);

        $this->postJson('/api/members', $minimal)
            ->assertCreated()
            ->assertJsonPath('data.profile.voice_group', 'B')
            ->assertJsonPath('data.profile.voice', null)
            ->assertJsonPath('data.profile.social_pseudo', null)
            ->assertJsonPath('data.profile.region_registration_number', null)
            ->assertJsonPath('data.profile.engagement_date', null)
            ->assertJsonPath('data.profile.engagement_place', null)
            ->assertJsonPath('data.profile.adhesion_date', '2019-09');

        $member = $this->createdMember();
        $this->assertNull(UserProfile::where('user_id', $member->id)->value('voice_id'));
        $this->assertNull(UserEngagement::where('user_id', $member->id)->value('engagement_date'));
    }

    public function test_the_account_fields_and_the_picture_cannot_be_set_through_this_endpoint(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload([
            'status'      => UserStatus::Pending->value,
            'roles'       => [RoleName::Admin->value,],
            'password'    => 'Choisi123!',
            'picture_url' => 'https://example.com/photo.jpg',
        ]))->assertCreated();

        $member = $this->createdMember();

        $this->assertSame(UserStatus::Active, $member->status);
        $this->assertSame([RoleName::Member->value,], $member->getRoleNames()->all());
        $this->assertFalse(Hash::check('Choisi123!', $member->password));
        $this->assertNull(UserProfile::where('user_id', $member->id)->value('picture_url'));
    }

    public function test_the_created_account_is_active_and_verified_with_the_member_role(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload())->assertCreated();

        $member = $this->createdMember();

        $this->assertSame(UserStatus::Active, $member->status);
        $this->assertNotNull($member->email_verified_at);
        $this->assertSame([RoleName::Member->value,], $member->getRoleNames()->all());
    }

    public function test_a_password_is_generated_and_stored_hashed(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload())
            ->assertCreated()
            ->assertJsonMissingPath('data.password');

        $member = $this->createdMember();
        $password = $this->sentInvitation()->password;

        $this->assertNotSame($password, $member->password);
        $this->assertTrue(Hash::check($password, $member->password));
        $this->assertSame(16, mb_strlen($password));
    }

    public function test_each_member_gets_a_different_password(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload())->assertCreated();
        $this->postJson('/api/members', $this->payload(['mail' => 'autre@example.com',]))->assertCreated();

        $passwords = Mail::queued(MemberInvitationMail::class)->map(fn (MemberInvitationMail $mail) => $mail->password);

        $this->assertCount(2, $passwords->unique());
    }

    public function test_the_invitation_mail_is_sent_to_the_member_with_their_credentials(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload())->assertCreated();

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            MemberInvitationMail::class,
            fn (MemberInvitationMail $mail): bool => $mail->hasTo(self::MAIL),
        );

        $mail = $this->sentInvitation();

        $mail->assertHasSubject('Bienvenue sur Kantica');
        $mail->assertSeeInHtml('Hantaniaina');
        $mail->assertSeeInHtml(self::MAIL);
        $mail->assertSeeInHtml($mail->password);
    }

    public function test_the_invitation_is_encrypted_in_the_queue_so_the_password_is_never_stored_in_clear(): void
    {
        $this->assertInstanceOf(
            ShouldBeEncrypted::class,
            new MemberInvitationMail('Hanta', 'hanta@example.com', 'secret'),
        );
    }

    public function test_the_member_can_log_in_with_the_password_from_the_mail(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload())->assertCreated();

        $this->postJson('/api/auth/login', [
            'mail'     => self::MAIL,
            'password' => $this->sentInvitation()->password,
        ])->assertOk()->assertJsonStructure(['token',]);
    }

    public function test_each_word_of_the_first_name_is_stored_as_a_first_name(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload(['first_name' => 'Hanta  Rasoa',]))
            ->assertCreated()
            ->assertJsonPath('data.profile.first_name', 'Hanta Rasoa');

        $firstnames = Firstname::where('user_id', $this->createdMember()->id)->orderBy('order')->get();

        $this->assertSame(['Hanta', 'Rasoa',], $firstnames->pluck('first_name')->all());
        $this->assertSame([true, false,], $firstnames->pluck('default')->all());
    }

    public function test_the_mail_is_trimmed_and_stored_in_lowercase(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload(['mail' => '  Hanta.Razafy@Example.COM ',]))
            ->assertCreated()
            ->assertJsonPath('data.mail', self::MAIL);

        $this->assertSame('Razafy', $this->createdMember()->last_name);
        Mail::assertQueued(
            MemberInvitationMail::class,
            fn (MemberInvitationMail $mail): bool => $mail->hasTo(self::MAIL),
        );
    }

    public function test_the_created_member_is_read_back_with_the_same_record_and_can_be_updated(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $id = $this->postJson('/api/members', $this->payload())->assertCreated()->json('data.id');

        $this->getJson("/api/members/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.profile', $this->expectedRecord(self::MAIL));

        $this->putJson("/api/members/{$id}", $this->recordPayload(['phone' => '034 00 111 22',]))
            ->assertOk()
            ->assertJsonPath('data.profile.phone', '034 00 111 22');
    }

    public function test_a_mail_already_used_is_refused(): void
    {
        $existing = $this->member('Rakoto', 'Jean', 'S');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $count = User::count();

        $this->postJson('/api/members', $this->payload(['mail' => $existing->mail,]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mail' => 'Cette adresse email est déjà utilisée.',]);

        $this->assertSame($count, User::count());
        Mail::assertNothingQueued();
    }

    public function test_a_mail_already_used_is_refused_whatever_its_case(): void
    {
        User::factory()->create(['mail' => 'Hanta.Razafy@Example.com',]);
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/members', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mail',]);

        $this->assertSame(0, User::where('last_name', 'Razafy')->count());
        Mail::assertNothingQueued();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_the_validations_are_the_same_as_the_record_form_and_nothing_is_created(array $overrides, string $field): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $count = User::count();

        $this->postJson('/api/members', $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field,]);

        $this->assertSame($count, User::count());
        $this->assertSame(0, UserProfile::count());
        $this->assertSame(0, UserSacrament::count());
        $this->assertSame(0, UserEmergencyContact::count());
        Mail::assertNothingQueued();
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        $contact = ['relationships' => ['Parents',], 'name' => 'Papa', 'phone' => '034 00 000 02',];

        return [
            'email manquant'             => [['mail' => null,], 'mail',],
            'email invalide'             => [['mail' => 'pas-un-email',], 'mail',],
            // 256 caractères : un de plus que la limite de 255
            'email trop long'            => [['mail' => str_repeat('a', 251).'@b.mg',], 'mail',],
            'nom manquant'               => [['last_name' => null,], 'last_name',],
            'nom trop long'              => [['last_name' => str_repeat('a', 101),], 'last_name',],
            'prénom manquant'            => [['first_name' => null,], 'first_name',],
            'prénom trop long'           => [['first_name' => str_repeat('a', 101),], 'first_name',],
            'groupe de voix manquant'    => [['voice_group' => null,], 'voice_group',],
            'groupe de voix inconnu'     => [['voice_group' => 'X',], 'voice_group',],
            'voix hors du groupe choisi' => [['voice_group' => 'A', 'voice' => 'B1',], 'voice',],
            'téléphone manquant'         => [['phone' => null,], 'phone',],
            'téléphone trop long'        => [['phone' => str_repeat('0', 31),], 'phone',],
            'naissance manquante'        => [['birth_date' => null,], 'birth_date',],
            'naissance sans jour'        => [['birth_date' => '03/1998',], 'birth_date',],
            'lieu de naissance manquant' => [['birth_place' => null,], 'birth_place',],
            'adresse manquante'          => [['address' => null,], 'address',],
            'adhésion manquante'         => [['adhesion_date' => null,], 'adhesion_date',],
            'adhésion dans le futur'     => [['adhesion_date' => '2999',], 'adhesion_date',],
            'baptême manquant'           => [['baptism' => null,], 'baptism',],
            'date du baptême manquante'  => [['baptism' => ['date' => null, 'place' => 'Ecar',],], 'baptism.date',],
            'lieu de la confirmation'    => [['confirmation' => ['date' => '2012', 'place' => null,],], 'confirmation.place',],
            'contact d\'urgence manquant' => [['emergency_contact' => null,], 'emergency_contact',],
            'liens du contact manquants' => [['emergency_contact' => [...$contact, 'relationships' => [],],], 'emergency_contact.relationships',],
            'lien avec une virgule'      => [['emergency_contact' => [...$contact, 'relationships' => ['Papa, Maman',],],], 'emergency_contact.relationships.0',],
            'nom du contact manquant'    => [['emergency_contact' => [...$contact, 'name' => null,],], 'emergency_contact.name',],
        ];
    }

    public function test_a_member_cannot_create_a_member(): void
    {
        $member = $this->member('Rakoto', 'Jean', 'S');
        Sanctum::actingAs($member);
        $count = User::count();

        $this->postJson('/api/members', $this->payload())->assertForbidden();
        // Le refus 403 passe avant la validation
        $this->postJson('/api/members', [])->assertForbidden();

        $this->assertSame($count, User::count());
        Mail::assertNothingQueued();
    }

    public function test_a_guest_cannot_create_a_member(): void
    {
        $this->postJson('/api/members', $this->payload())->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['mail' => self::MAIL,]);
        Mail::assertNothingQueued();
    }

    public function test_a_pending_secretary_cannot_create_a_member(): void
    {
        $pending = User::factory()->pending()->create();
        $pending->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($pending);

        $this->postJson('/api/members', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);

        $this->assertDatabaseMissing('users', ['mail' => self::MAIL,]);
        Mail::assertNothingQueued();
    }
}
