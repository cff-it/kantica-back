<?php

namespace App\Actions\Auth;

use App\Models\User;

readonly class LogoutAction
{
    public function __invoke(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
