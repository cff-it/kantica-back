<?php

namespace App\DTOs\User;

class UsersImportReportDTO
{
    /**
     * @param  list<UserImportIssueDTO>  $skipped
     * @param  list<UserImportIssueDTO>  $errors
     * @param  list<UserImportIssueDTO>  $warnings  informations illisibles des comptes créés : le compte existe sans elles
     * @param  list<string>  $ignoredColumns  colonnes du fichier que l'import ne reprend pas
     */
    public function __construct(
        public readonly int $total,
        public readonly int $created,
        public readonly array $skipped,
        public readonly array $errors,
        public readonly array $warnings = [],
        public readonly array $ignoredColumns = [],
    ) {}
}
