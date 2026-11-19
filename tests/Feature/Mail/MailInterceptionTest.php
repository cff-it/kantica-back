<?php

namespace Tests\Feature\Mail;

use App\Mail\MemberInvitationMail;
use App\Notifications\AccountValidatedNotification;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailInterceptionTest extends TestCase
{
    public function test_a_mailable_is_redirected_to_the_interception_address(): void
    {
        $this->bootInterception('intercepted@example.com');

        Mail::to('member@example.com')->send(new MemberInvitationMail('Hanta', 'member@example.com', 'Secret123'));

        $this->assertSame(['intercepted@example.com',], $this->recipients());
    }

    public function test_a_notification_is_redirected_to_the_interception_address(): void
    {
        $this->bootInterception('intercepted@example.com');

        Notification::route('mail', 'member@example.com')->notify(new AccountValidatedNotification());

        $this->assertSame(['intercepted@example.com',], $this->recipients());
    }

    public function test_mails_are_not_intercepted_when_the_address_is_empty(): void
    {
        $this->bootInterception('');

        Mail::to('member@example.com')->send(new MemberInvitationMail('Hanta', 'member@example.com', 'Secret123'));

        $this->assertSame(['member@example.com',], $this->recipients());
    }

    public function test_mails_are_never_intercepted_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->bootInterception('intercepted@example.com');

        Mail::to('member@example.com')->send(new MemberInvitationMail('Hanta', 'member@example.com', 'Secret123'));

        $this->assertSame(['member@example.com',], $this->recipients());
    }

    private function bootInterception(string $alwaysTo): void
    {
        config(['mail.always_to' => $alwaysTo,]);

        (new AppServiceProvider($this->app))->boot();
    }

    /**
     * Destinataires réels de tous les mails passés par le transport `array`.
     *
     * @return array<int, string>
     */
    private function recipients(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()
            ->flatMap(fn ($sent) => array_map(
                fn ($recipient) => $recipient->getAddress(),
                $sent->getEnvelope()->getRecipients(),
            ))
            ->values()
            ->all();
    }
}
