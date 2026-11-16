<?php

namespace App\DTOs\User;

class UsersImportAnalysisDTO
{
    /**
     * @param  list<UserImportRowDTO>  $ready  lignes prêtes à être importées
     * @param  list<UserImportIssueDTO>  $skipped  lignes ignorées : un compte existe déjà avec cet email
     * @param  list<UserImportIssueDTO>  $errors  lignes invalides, à corriger dans le fichier
     * @param  list<UserImportIssueDTO>  $warnings  informations illisibles des lignes prêtes : la ligne est importée sans elles
     * @param  list<string>  $ignoredColumns  colonnes du fichier que l'import ne reprend pas
     */
    public function __construct(
        public readonly int $total,
        public readonly array $ready,
        public readonly array $skipped,
        public readonly array $errors,
        public readonly array $warnings = [],
        public readonly array $ignoredColumns = [],
    ) {}
}
