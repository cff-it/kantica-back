<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'user' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage())
            ->subject('Vérifiez votre adresse email Kantica')
            ->greeting('Bonjour,')
            ->line('Merci pour votre inscription sur Kantica.')
            ->line('Cliquez sur le bouton ci-dessous pour confirmer votre adresse email.')
            ->action('Vérifier mon adresse email', $url)
            ->line('Si vous n\'êtes pas à l\'origine de cette inscription, ignorez cet email.');
    }
}
