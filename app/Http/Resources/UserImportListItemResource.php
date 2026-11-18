<?php

namespace App\Http\Resources;

use App\Models\UserImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Ligne de l'historique des imports : qui, quand, état et chiffres du rapport. Le rapport complet est dans le détail.
 *
 * @mixin UserImport
 */
class UserImportListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Statut, avancement, auteur et dates suivent le format du détail : une seule définition à maintenir
        $detail = UserImportResource::make($this->resource)->resolve($request);

        return [
            ...Arr::only($detail, ['id', 'status', 'progress', 'user', 'created_at', 'finished_at', 'error',]),
            'summary' => $this->summary(),
        ];
    }

    /**
     * Chiffres du rapport ; null tant que l'import n'est pas terminé (ou s'il a échoué avant d'écrire son rapport).
     *
     * @return array<string, int>|null
     */
    private function summary(): ?array
    {
        if ($this->report === null) {
            return null;
        }

        return Arr::only($this->report, ['total', 'created', 'skipped_count', 'error_count', 'warning_count',]);
    }
}
