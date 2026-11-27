<?php

namespace App\DTOs\User;

use App\Enums\RoleName;

class AssignRoleDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly RoleName $role,
    ) {}
}
