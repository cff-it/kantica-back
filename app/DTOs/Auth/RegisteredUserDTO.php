<?php

namespace App\DTOs\Auth;

use App\Models\User;

class RegisteredUserDTO
{
    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {}
}
