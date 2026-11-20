<?php

namespace Tests\Feature\Mail;

use App\Listeners\EmbedMailLogo;
use App\Mail\MemberInvitationMail;
use App\Notifications\AccountValidatedNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailLogoTest extends TestCase
{
    public function test_the_choir_logo_is_embedded_once_in_a_markdown_mailable(): void
    {
        Mail::to('member@example.com')->send(new MemberInvitationMail('Hanta', 'member@example.com', 'Secret123'));

        $this->assertLogoEmbeddedOnce($this->sentEmail());
    }

    public function test_the_choir_logo_is_embedded_once_in_a_markdown_notification(): void
    {
        Notification::route('mail', 'member@example.com')->notify(new AccountValidatedNotification());

        $this->assertLogoEmbeddedOnce($this->sentEmail());
    }

    public function test_the_laravel_logo_is_gone_from_the_mail_header(): void
    {
        Mail::to('member@example.com')->send(new MemberInvitationMail('Hanta', 'member@example.com', 'Secret123'));

        $this->assertStringNotContainsString('laravel.com/img', $this->sentEmail()->getHtmlBody());
    }

    public function test_a_mail_without_the_logo_reference_gets_no_attachment(): void
    {
        Mail::raw('Texte brut', fn ($message) => $message->to('member@example.com')->subject('Test'));

        $this->assertCount(0, $this->sentEmail()->getAttachments());
    }

    private function assertLogoEmbeddedOnce(Email $email): void
    {
        $this->assertStringContainsString('cid:'.EmbedMailLogo::CONTENT_ID, $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());

        $logo = $email->getAttachments()[0];

        $this->assertSame('image', $logo->getMediaType());
        $this->assertSame('png', $logo->getMediaSubtype());
        $this->assertSame('inline', $logo->getDisposition());
    }

    private function sentEmail(): Email
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }
}
