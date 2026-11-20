<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

/**
 * Joint le logo de la chorale (image inline, `cid:logo-cff`) aux mails dont le HTML le référence.
 *
 * L'image est embarquée dans le mail plutôt que chargée depuis une URL : elle s'affiche aussi en local
 * (`APP_URL=http://localhost` n'est pas joignable depuis la messagerie du destinataire) et sans blocage des images
 * distantes. La référence se trouve dans `resources/views/vendor/mail/html/header.blade.php`.
 */
class EmbedMailLogo
{
    public const CONTENT_ID = 'logo-cff';

    public function handle(MessageSending $event): void
    {
        if (!str_contains((string) $event->message->getHtmlBody(), 'cid:'.self::CONTENT_ID)) {
            return;
        }

        $event->message->embedFromPath(resource_path('images/logo-cff.png'), self::CONTENT_ID, 'image/png');
    }
}
