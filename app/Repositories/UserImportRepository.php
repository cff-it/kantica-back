<?php

namespace App\Repositories;

use App\Enums\UserImportStatus;
use App\Models\UserImport;

class UserImportRepository
{
    public function create(int $userId, string $filePath, int $total): UserImport
    {
        return UserImport::create([
            'user_id'   => $userId,
            'status'    => UserImportStatus::Pending,
            'file_path' => $filePath,
            'total'     => $total,
        ]);
    }

    public function findById(int $id): UserImport
    {
        return UserImport::findOrFail($id);
    }

    // Début du traitement : le total est celui de l'analyse faite au moment d'écrire (des membres ont pu apparaître depuis l'envoi)
    public function start(UserImport $import, int $total): void
    {
        $import->update([
            'status'    => UserImportStatus::Running,
            'total'     => $total,
            'processed' => 0,
        ]);
    }

    // Une seule requête `processed = processed + n` : le front lit l'avancement pendant que le traitement écrit
    public function advance(UserImport $import, int $steps): void
    {
        $import->increment('processed', $steps);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function complete(UserImport $import, array $report): void
    {
        $import->update([
            'status'    => UserImportStatus::Completed,
            'processed' => $import->total,
            'report'    => $report,
            'file_path' => null,
        ]);
    }

    public function fail(UserImport $import, string $message): void
    {
        $import->update([
            'status'        => UserImportStatus::Failed,
            'error_message' => $message,
            'file_path'     => null,
        ]);
    }
}
