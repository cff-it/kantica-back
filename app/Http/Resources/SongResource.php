<?php

namespace App\Http\Resources;

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
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
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
