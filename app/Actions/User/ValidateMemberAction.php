<?php

namespace App\Actions\User;

use App\Models\User;
use App\Notifications\AccountValidatedNotification;
use App\Repositories\UserRepository;

readonly class ValidateMemberAction
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function __invoke(User $member): User
    {
        // Mise à jour conditionnelle : un compte déjà actif reste inchangé (idempotent)
        if ($this->users->activate($member)) {
            $member->notify(new AccountValidatedNotification());
        }

        return $member->refresh()->load('roles');
    }
}
