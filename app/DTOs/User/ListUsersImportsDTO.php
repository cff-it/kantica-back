<?php

namespace App\DTOs\User;

class ListUsersImportsDTO
{
    public function __construct(
        public readonly int $viewerId,
        public readonly bool $viewerIsAdmin,
        public readonly int $page,
    ) {}
}
