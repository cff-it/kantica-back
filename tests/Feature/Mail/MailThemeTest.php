<?php

namespace Tests\Feature\Mail;

use App\Mail\MemberInvitationMail;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailThemeTest extends TestCase
{
    public function test_the_mail_body_uses_the_cff_colors(): void
    {
        Mail::to('member@example.com')->send(new MemberInvitationMail('Hanta', 'member@example.com', 'Secret123'));

        $html = $this->sentEmail()->getHtmlBody();

        $this->assertStringContainsString('#2f5dae', $html, 'Bleu texte : titre et liens');
        $this->assertStringContainsString('#3a6fc9', $html, 'Bleu CFF : filet du panneau');
        $this->assertStringContainsString('#eef2fa', $html, 'Fond du panneau');
        $this->assertStringContainsString('#e8231a', $html, 'Rouge CFF : filet de la carte');
    }

    public function test_the_default_laravel_palette_is_gone_from_the_mail_body(): void
    {
        Mail::to('member@example.com')->send(new MemberInvitationMail('Hanta', 'member@example.com', 'Secret123'));

        $html = $this->sentEmail()->getHtmlBody();

        $this->assertStringNotContainsString('#18181b', $html);
        $this->assertStringNotContainsString('#52525b', $html);
        $this->assertStringNotContainsString('#fafafa', $html);
    }

    private function sentEmail(): Email
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }
}
