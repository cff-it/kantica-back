<?php

namespace App\Http\Resources;

use App\Models\MassSong;
use App\Models\SongSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'uuid'       => $this->uuid,
            'title'      => $this->title,
            'author'     => $this->author,
            'reference'  => $this->reference,
            'notes'      => $this->notes,
            'sections'   => $this->whenLoaded('sections', fn () => $this->sectionsInOrder()),
            'usage'      => $this->whenLoaded('massSongs', fn () => $this->usage()),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Où et quand le cantique a été chanté, pour éviter les répétitions et équilibrer le répertoire.
     * `history` va de la messe la plus récente à la plus ancienne ; `last_sung` en est le premier élément (null si jamais chanté).
     * `occasion` est le nom liturgique de la messe (null s'il n'est pas renseigné), `part` la partie de la messe (Entrée, Communion...).
     *
     * @return array{times_sung: int, last_sung: array<string, mixed>|null, history: list<array<string, mixed>>}
     */
    private function usage(): array
    {
        $history = $this->massSongs
            ->sortBy([
                [fn (MassSong $massSong) => $massSong->massPart->mass->date->toDateString(), 'desc',],
                [fn (MassSong $massSong) => $massSong->massPart->mass_id, 'desc',],
            ])
            ->map(fn (MassSong $massSong) => [
                'mass_id'  => $massSong->massPart->mass_id,
                'date'     => $massSong->massPart->mass->date->toDateString(),
                'occasion' => $massSong->massPart->mass->liturgical_name,
                'part'     => $massSong->massPart->type->name,
            ])
            ->values();

        return [
            'times_sung' => $history->count(),
            'last_sung'  => $history->first(),
            'history'    => $history->all(),
        ];
    }

    /**
     * Sections dans l'ordre de saisie. `number` est le numéro saisi (Couplet 1, 2, 3) : null s'il n'y en a pas.
     *
     * @return list<array<string, mixed>>
     */
    private function sectionsInOrder(): array
    {
        return $this->sections->map(fn (SongSection $section) => [
            'id'       => $section->id,
            'uuid'     => $section->uuid,
            'type'     => $section->type,
            'number'   => $section->number,
            'text'     => $section->lyrics,
            'position' => $section->position,
        ])->all();
    }
}
