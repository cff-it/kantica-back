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

    // Étapes de l'import, base du pourcentage : un compte à créer par ligne prête, plus une photo à télécharger
    // pour chaque ligne qui en a une
    public function steps(): int
    {
        $withPicture = array_filter($this->ready, fn (UserImportRowDTO $row): bool => $row->pictureUrl !== null);

        return count($this->ready) + count($withPicture);
    }
}
