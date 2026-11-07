<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MassPartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'uuid'     => $this->uuid,
            'type'     => $this->whenLoaded('type', fn () => [
                'id'   => $this->type->id,
                'name' => $this->type->name,
            ]),
            'antiphon' => $this->antiphon,
            'prayer'   => $this->whenLoaded('prayer', fn () => $this->prayer?->title),
            'songs'    => $this->whenLoaded(
                'massSongs',
                fn () => $this->massSongs->map(fn ($ms) => [
                    'id'             => $ms->id,
                    'song'           => SongResource::make($ms->song),
                    'specific_title' => $ms->specific_title,
                ]),
            ),
        ];
    }
}
