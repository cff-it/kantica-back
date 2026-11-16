<?php

namespace App\Actions\User;

use App\Models\UserImport;
use App\Repositories\UserImportRepository;
use Illuminate\Support\Facades\Storage;

// Marque un import comme échoué (erreur ou délai dépassé) et supprime le fichier envoyé. Appelée par `ImportUsersJob::failed()`.
// Les membres déjà créés sont conservés : relancer le même fichier crée les autres, les premiers sont ignorés.
readonly class FailUsersImportAction
{
    public function __construct(
        private UserImportRepository $imports,
    ) {}

    public function __invoke(int $userImportId): void
    {
        $import = $this->imports->findById($userImportId);

        if ($import->file_path !== null) {
            Storage::disk(UserImport::FILE_DISK)->delete($import->file_path);
        }

        $this->imports->fail(
            $import,
            "L'import a été interrompu par une erreur. Les membres déjà créés sont conservés : relancez le même fichier pour créer les autres.",
        );
    }
}
