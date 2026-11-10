<?php

namespace App\DTOs\User;

class AssignRoleDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $role,
    ) {}
}
