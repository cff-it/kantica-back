<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invitation d'un membre ajouté par la secrétaire ou l'admin (KAN-89) : ses identifiants de connexion.
 *
 * Le mot de passe est celui généré à la création du compte. Le mail passe par la file d'attente : `ShouldBeEncrypted`
 * chiffre le job, pour que le mot de passe ne soit jamais écrit en clair dans la table `jobs`.
 */
class MemberInvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $firstName,
        public readonly string $mail,
        public readonly string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bienvenue sur Kantica');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.member-invitation');
    }
}
