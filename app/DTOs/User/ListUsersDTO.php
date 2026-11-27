<?php

namespace App\DTOs\User;

class ListUsersDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly int $page,
    ) {}
}
