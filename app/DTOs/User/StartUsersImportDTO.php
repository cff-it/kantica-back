<?php

namespace App\DTOs\User;

class StartUsersImportDTO
{
    public function __construct(
        public readonly string $path,
        public readonly int $userId,
        public readonly string $fileName,
    ) {}
}
