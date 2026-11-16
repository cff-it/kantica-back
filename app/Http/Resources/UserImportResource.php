<?php

namespace App\Http\Resources;

use App\Enums\UserImportStatus;
use App\Models\UserImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserImport
 */
class UserImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'status'   => $this->status->value,
            // `processed` et `total` comptent des étapes (un compte, une photo), pas des lignes du fichier
            'progress' => [
                'processed' => $this->processed,
                'total'     => $this->total,
                'percent'   => $this->percent(),
            ],
            // Rapport final : même contenu que l'ancienne réponse de `POST /api/users/import` ; null tant que l'import n'est pas terminé
            'report' => $this->report,
            // Raison de l'arrêt quand le statut est `failed`
            'error' => $this->error_message,
        ];
    }

    // 100 seulement une fois l'import terminé : tant que le rapport n'est pas écrit, on ne dépasse pas 99
    private function percent(): int
    {
        if ($this->status === UserImportStatus::Completed) {
            return 100;
        }

        if ($this->total <= 0) {
            return 0;
        }

        return min(99, intdiv($this->processed * 100, $this->total));
    }
}
