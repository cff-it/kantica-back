<?php

namespace App\DTOs\User;

class CreateUserDTO
{
    public function __construct(
        public readonly string $mail,
        public readonly string $lastName,
        public readonly string $password,
        public readonly ?string $nickName = null,
    ) {}
}
