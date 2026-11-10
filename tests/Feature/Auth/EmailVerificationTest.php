<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_signed_link_verifies_the_email(): void
    {
        Event::fake([Verified::class,]);
        $user = User::factory()->pending()->unverified()->create();

        $this->getJson($this->verificationUrl($user))
            ->assertOk()
            ->assertJsonPath('message', 'Adresse email vérifiée.');

        $this->assertNotNull($user->refresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_verifying_an_already_verified_email_has_no_effect(): void
    {
        Event::fake([Verified::class,]);
        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at;

        $this->getJson($this->verificationUrl($user))->assertOk();

        $this->assertEquals($verifiedAt, $user->refresh()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }

    public function test_a_link_without_valid_signature_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $hash = sha1($user->mail);

        $this->getJson("/api/email/verify/{$user->id}/{$hash}")->assertForbidden();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_a_link_with_a_wrong_hash_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'user' => $user->id,
                'hash' => sha1('autre@example.com'),
            ],
        );

        $this->getJson($url)->assertForbidden();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_an_expired_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        $this->travel(2)->hours();

        $this->getJson($url)->assertForbidden();
    }

    public function test_the_verification_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->pending()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Email de vérification envoyé.');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_no_email_is_resent_when_already_verified(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Adresse email déjà vérifiée.');

        Notification::assertNothingSent();
    }

    public function test_the_verification_link_sent_by_email_works(): void
    {
        $user = User::factory()->pending()->unverified()->create();

        $url = (new VerifyEmailNotification())->toMail($user)->actionUrl;

        $this->getJson($url)->assertOk();

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'user' => $user->id,
                'hash' => sha1($user->mail),
            ],
        );
    }
}
