<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;

readonly class VerifyEmailAction
{
    // Le lien signé est ouvert depuis l'email : pas de token, l'identité est portée par l'id et le hash
    public function __invoke(User $user, string $hash): void
    {
        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AuthorizationException('Lien de vérification invalide.');
        }

        // Un email déjà vérifié reste inchangé (idempotent)
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->markEmailAsVerified();

        event(new Verified($user));
    }
}
