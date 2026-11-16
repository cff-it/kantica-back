<?php

namespace App\DTOs\User;

class ImportUsersDTO
{
    public function __construct(
        public readonly string $path,
    ) {}
}
