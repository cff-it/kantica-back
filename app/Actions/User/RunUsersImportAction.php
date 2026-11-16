<?php

namespace App\Actions\User;

use App\DTOs\User\ImportUsersDTO;
use App\Enums\UserImportStatus;
use App\Models\UserImport;
use App\Repositories\UserImportRepository;
use Illuminate\Support\Facades\Storage;

// Exécute un import mis en file d'attente : écrit les membres en faisant avancer le suivi, garde le rapport, puis
// supprime le fichier envoyé. Appelée par `ImportUsersJob`.
readonly class RunUsersImportAction
{
    public function __construct(
        private UserImportRepository $imports,
        private ImportUsersAction $import,
    ) {}

    public function __invoke(int $userImportId): void
    {
        $import = $this->imports->findById($userImportId);

        // Un import déjà pris en charge ou terminé n'est jamais rejoué (le Job peut être livré deux fois)
        if ($import->status !== UserImportStatus::Pending || $import->file_path === null) {
            return;
        }

        $path = $import->file_path;
        $disk = Storage::disk(UserImport::FILE_DISK);

        $report = ($this->import)(new ImportUsersDTO($disk->path($path)), $import);

        $this->imports->complete($import, $report->toArray());
        $disk->delete($path);
    }
}
