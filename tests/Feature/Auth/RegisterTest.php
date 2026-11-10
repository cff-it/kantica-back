<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    public function test_a_choir_member_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'mail'                  => 'choriste@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'mail', 'status', 'roles',],])
            ->assertJsonPath('user.mail', 'choriste@example.com')
            ->assertJsonPath('user.status', UserStatus::Pending->value)
            ->assertJsonPath('user.roles', ['membre',]);

        $user = User::where('mail', 'choriste@example.com')->firstOrFail();

        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertNotSame('Password123!', $user->password);
        $this->assertTrue($user->hasRole('membre'));
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_the_returned_token_authenticates_the_new_user(): void
    {
        $token = $this->postJson('/api/register', [
            'mail'                  => 'choriste@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.mail', 'choriste@example.com')
            ->assertJsonPath('data.status', UserStatus::Pending->value)
            ->assertJsonPath('data.roles', ['membre',]);
    }

    public function test_a_registered_user_can_log_in(): void
    {
        $this->postJson('/api/register', [
            'mail'                  => 'choriste@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $this->postJson('/api/auth/login', [
            'mail'     => 'choriste@example.com',
            'password' => 'Password123!',
        ])->assertOk()->assertJsonStructure(['token',]);
    }

    public function test_registration_is_refused_when_the_email_is_already_used(): void
    {
        User::factory()->create(['mail' => 'choriste@example.com',]);

        $this->postJson('/api/register', [
            'mail'                  => 'choriste@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['mail' => 'Cette adresse email est déjà utilisée.',]);

        $this->assertSame(1, User::where('mail', 'choriste@example.com')->count());
    }

    public function test_registration_is_refused_when_the_password_is_too_short(): void
    {
        $this->postJson('/api/register', [
            'mail'                  => 'choriste@example.com',
            'password'              => 'Ab1!',
            'password_confirmation' => 'Ab1!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'Le mot de passe doit contenir au moins 8 caractères.',]);

        $this->assertDatabaseMissing('users', ['mail' => 'choriste@example.com',]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'sans majuscule'         => ['password123!', 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',],
            'sans minuscule'         => ['PASSWORD123!', 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',],
            'sans chiffre'           => ['Password!!!', 'Le mot de passe doit contenir au moins un chiffre.',],
            'sans caractère spécial' => ['Password123', 'Le mot de passe doit contenir au moins un caractère spécial.',],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_registration_is_refused_when_the_password_is_too_weak(string $password, string $message): void
    {
        $this->postJson('/api/register', [
            'mail'                  => 'choriste@example.com',
            'password'              => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => $message,]);

        $this->assertDatabaseMissing('users', ['mail' => 'choriste@example.com',]);
    }

    public function test_registration_is_refused_when_the_confirmation_does_not_match(): void
    {
        $this->postJson('/api/register', [
            'mail'                  => 'choriste@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password456!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password',]);
    }

    public function test_registration_is_refused_when_the_email_is_invalid(): void
    {
        $this->postJson('/api/register', [
            'mail'                  => 'not-an-email',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['mail',]);
    }

    public function test_registration_is_refused_when_fields_are_missing(): void
    {
        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mail', 'password',]);
    }

    public function test_a_pending_account_is_blocked_on_business_routes(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->getJson('/api/songs')
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);
    }

    public function test_an_unverified_account_is_blocked_on_business_routes(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->getJson('/api/songs')
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::EMAIL_NOT_VERIFIED_CODE);
    }

    public function test_a_pending_account_can_log_out(): void
    {
        $token = $this->postJson('/api/register', [
            'mail'                  => 'choriste@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertNoContent();
    }

    public function test_registration_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
        }

        $this->postJson('/api/register', [])->assertTooManyRequests();
    }
}
