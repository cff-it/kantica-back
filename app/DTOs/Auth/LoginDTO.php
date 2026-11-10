<?php

namespace App\DTOs\Auth;

class LoginDTO
{
    public function __construct(
        public readonly string $mail,
        public readonly string $password,
    ) {}
}
