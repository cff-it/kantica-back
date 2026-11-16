<?php

namespace App\DTOs\User;

// Ligne du fichier qui ne sera pas importée (erreur à corriger ou compte déjà existant)
class UserImportIssueDTO
{
    public function __construct(
        public readonly int $line,
        public readonly ?string $name,
        public readonly string $message,
    ) {}
}
