<?php

namespace App\Http\Resources;

use App\DTOs\Song\SongDetailDTO;
use App\DTOs\Song\SongUseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Le cantique de SongResource avec son historique d'usage (`GET /api/songs/{song}`), jamais renvoyé par la liste, par
 * l'ajout ni par la modification.
 *
 * `usage.times_sung` : nombre de fois où le cantique a été chanté. `usage.history` : les utilisations les plus récentes,
 * de la plus récente à la plus ancienne ; `usage.last_sung` en est le premier élément (null si jamais chanté).
 * `occasion` est le nom liturgique de la messe (null s'il n'est pas renseigné), `part` la partie de la messe.
 *
 * @property SongDetailDTO $resource
 */
class SongDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usage = $this->resource->usage;

        return [
            ...SongResource::make($this->resource->song)->resolve($request),
            'usage' => [
                'times_sung' => $usage->timesSung,
                'last_sung'  => $usage->lastSung ? $this->entry($usage->lastSung) : null,
                'history'    => array_map(fn (SongUseDTO $use) => $this->entry($use), $usage->history),
            ],
        ];
    }

    /**
     * @return array{mass_id: int, date: string, occasion: string|null, part: string}
     */
    private function entry(SongUseDTO $use): array
    {
        return [
            'mass_id'  => $use->massId,
            'date'     => $use->date,
            'occasion' => $use->occasion,
            'part'     => $use->part,
        ];
    }
}
