<?php

namespace App\Actions\Auth;

use App\Models\User;

readonly class ResendEmailVerificationAction
{
    // Retourne false si l'email est déjà vérifié (aucun envoi)
    public function __invoke(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();

        return true;
    }
}
