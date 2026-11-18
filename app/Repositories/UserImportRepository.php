<?php

namespace App\Repositories;

use App\Enums\UserImportStatus;
use App\Models\UserImport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class UserImportRepository
{
    public function create(int $userId, string $filePath, int $total, string $fileName): UserImport
    {
        return UserImport::create([
            'user_id'   => $userId,
            'status'    => UserImportStatus::Pending,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'total'     => $total,
        ]);
    }

    public function findById(int $id): UserImport
    {
        return UserImport::findOrFail($id);
    }

    /**
     * Historique : du plus récent au plus ancien ; `$ownerId` null = tous les imports. L'auteur et ses prénoms sont
     * chargés d'avance pour que la liste ne fasse pas une requête par ligne.
     *
     * @return LengthAwarePaginator<int, UserImport>
     */
    public function paginate(?int $ownerId, int $page, int $perPage): LengthAwarePaginator
    {
        return UserImport::query()
            ->with('user.firstnames')
            ->when($ownerId !== null, fn (Builder $query) => $query->where('user_id', $ownerId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, page: $page);
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
