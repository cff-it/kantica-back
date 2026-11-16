<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountValidatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail',];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Votre compte Kantica est validé')
            ->greeting('Bonjour,')
            ->line('Votre compte Kantica a été validé par la chorale.')
            ->line('Vous avez désormais accès à l\'ensemble de l\'application.');
    }
}
