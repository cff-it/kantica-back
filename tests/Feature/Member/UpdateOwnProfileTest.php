<?php

namespace Tests\Feature\Member;

use App\Models\Firstname;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Voice;
use App\Models\VoiceGroup;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateOwnProfileTest extends TestCase
{
    use RefreshDatabase;

    private const string MAIL = 'hanta.rasoa@exemple.com';

    private const array PAYLOAD = [
        'last_name'   => 'Rasoanaivo',
        'first_name'  => 'Hanta',
        'voice_group' => 'A',
        'voice'       => 'A2',
        'phone'       => '034 12 345 67',
    ];

    // L'email de contact renvoyé est l'email du compte
    private const array EXPECTED = [
        ...self::PAYLOAD,
        'contact_mail' => self::MAIL,
        'completed'    => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VoiceSeeder::class);
    }

    public function test_a_pending_member_can_create_their_profile(): void
    {
        $user = User::factory()->pending()->create(['mail' => self::MAIL, 'last_name' => null,]);
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', self::PAYLOAD)
            ->assertOk()
            ->assertExactJson(['data' => self::EXPECTED,]);

        $this->assertSame('Rasoanaivo', $user->refresh()->last_name);
        $this->assertDatabaseHas('firstnames', [
            'user_id'    => $user->id,
            'first_name' => 'Hanta',
            'default'    => true,
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id'        => $user->id,
            'voice_group_id' => VoiceGroup::where('abbreviation', 'A')->value('id'),
            'voice_id'       => Voice::where('name', 'A2')->value('id'),
            'phone'          => '034 12 345 67',
        ]);
    }

    public function test_the_precise_voice_is_optional(): void
    {
        $user = User::factory()->pending()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', [...self::PAYLOAD, 'voice' => null,])
            ->assertOk()
            ->assertJsonPath('data.voice_group', 'A')
            ->assertJsonPath('data.voice', null);

        $this->assertDatabaseHas('user_profiles', [
            'user_id'        => $user->id,
            'voice_group_id' => VoiceGroup::where('abbreviation', 'A')->value('id'),
            'voice_id'       => null,
        ]);
    }

    public function test_a_voice_from_another_group_is_refused(): void
    {
        $user = User::factory()->pending()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', [...self::PAYLOAD, 'voice' => 'S1',])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice' => 'Cette voix n\'existe pas dans le groupe de voix choisi.',]);

        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id,]);
    }

    public function test_a_contact_mail_sent_by_the_client_is_ignored(): void
    {
        $user = User::factory()->pending()->create(['mail' => self::MAIL,]);
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', [...self::PAYLOAD, 'contact_mail' => 'autre@exemple.com',])
            ->assertOk()
            ->assertJsonPath('data.contact_mail', self::MAIL);

        $this->assertSame(self::MAIL, $user->refresh()->mail);
    }

    public function test_submitting_again_updates_the_existing_profile(): void
    {
        $user = User::factory()->pending()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', self::PAYLOAD)->assertOk();
        $this->putJson('/api/me/profile', [
            ...self::PAYLOAD,
            'first_name'  => 'Hantaniaina',
            'voice_group' => 'S',
            'voice'       => 'S1',
        ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Hantaniaina')
            ->assertJsonPath('data.voice_group', 'S')
            ->assertJsonPath('data.voice', 'S1');

        $this->assertSame(1, UserProfile::where('user_id', $user->id)->count());
        $this->assertSame(1, Firstname::where('user_id', $user->id)->count());
    }

    public function test_an_empty_profile_is_returned_before_the_first_submission(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create(['mail' => self::MAIL, 'last_name' => null,]));

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'last_name'    => null,
                    'first_name'   => null,
                    'voice_group'  => null,
                    'voice'        => null,
                    'phone'        => null,
                    'contact_mail' => self::MAIL,
                    'completed'    => false,
                ],
            ]);
    }

    public function test_the_saved_profile_is_returned(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create(['mail' => self::MAIL,]));

        $this->putJson('/api/me/profile', self::PAYLOAD)->assertOk();

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertExactJson(['data' => self::EXPECTED,]);
    }

    public function test_me_returns_the_saved_profile(): void
    {
        $user = User::factory()->pending()->create(['mail' => self::MAIL,]);
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', self::PAYLOAD)->assertOk();

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.profile', self::EXPECTED)
            ->assertJsonPath('data.firstnames.0.first_name', 'Hanta');
    }

    public function test_me_returns_an_empty_profile_before_the_first_submission(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create(['mail' => self::MAIL, 'last_name' => null,]));

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.profile.completed', false)
            ->assertJsonPath('data.profile.contact_mail', self::MAIL)
            ->assertJsonPath('data.firstnames', [])
            ->assertJsonMissingPath('data.voice');
    }

    public function test_an_invalid_voice_group_is_refused(): void
    {
        $user = User::factory()->pending()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', [...self::PAYLOAD, 'voice_group' => 'X', 'voice' => null,])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice_group' => 'Le groupe de voix doit être S, A, T ou B.',]);

        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id,]);
    }

    public function test_all_fields_are_required(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->putJson('/api/me/profile', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name', 'first_name', 'voice_group', 'phone',])
            ->assertJsonMissingValidationErrors(['voice', 'contact_mail',]);
    }

    public function test_only_the_current_user_profile_is_modified(): void
    {
        $other = User::factory()->create(['last_name' => 'Autre',]);
        $user = User::factory()->pending()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', [...self::PAYLOAD, 'user_id' => $other->id,])->assertOk();

        $this->assertSame('Autre', $other->refresh()->last_name);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $other->id,]);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id,]);
    }

    public function test_an_unverified_account_can_fill_its_profile(): void
    {
        Sanctum::actingAs(User::factory()->pending()->unverified()->create());

        $this->putJson('/api/me/profile', self::PAYLOAD)->assertOk();
    }

    public function test_a_guest_cannot_access_the_profile(): void
    {
        $this->getJson('/api/me/profile')->assertUnauthorized();
        $this->putJson('/api/me/profile', self::PAYLOAD)->assertUnauthorized();
    }
}
