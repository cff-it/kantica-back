<?php

namespace App\DTOs\User;

class UsersImportReportDTO
{
    /**
     * @param  list<UserImportIssueDTO>  $skipped
     * @param  list<UserImportIssueDTO>  $errors
     * @param  list<UserImportIssueDTO>  $warnings  informations illisibles des comptes créés : le compte existe sans elles
     * @param  list<string>  $ignoredColumns  colonnes du fichier que l'import ne reprend pas
     * @param  list<UserImportRowDTO>  $imported  membres réellement créés (pas les lignes ignorées ni en erreur)
     */
    public function __construct(
        public readonly int $total,
        public readonly int $created,
        public readonly array $skipped,
        public readonly array $errors,
        public readonly array $warnings = [],
        public readonly array $ignoredColumns = [],
        public readonly array $imported = [],
    ) {}

    /**
     * Forme enregistrée dans `user_imports.report` et renvoyée au front dans `report`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $issue = fn (UserImportIssueDTO $issue): array => $issue->toArray();

        return [
            'total'           => $this->total,
            'created'         => $this->created,
            'skipped_count'   => count($this->skipped),
            'error_count'     => count($this->errors),
            'warning_count'   => count($this->warnings),
            // Même forme que `ready` de l'aperçu ; absent des rapports enregistrés avant son ajout
            'imported'        => array_map(fn (UserImportRowDTO $row): array => $row->toReviewArray(), $this->imported),
            'skipped'         => array_map($issue, $this->skipped),
            'errors'          => array_map($issue, $this->errors),
            'warnings'        => array_map($issue, $this->warnings),
            'ignored_columns' => $this->ignoredColumns,
        ];
    }
}
