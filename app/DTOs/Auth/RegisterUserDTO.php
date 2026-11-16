<?php

namespace App\DTOs\Auth;

class RegisterUserDTO
{
    public function __construct(
        public readonly string $mail,
        public readonly string $password,
    ) {}
}
