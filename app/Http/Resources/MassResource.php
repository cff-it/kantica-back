<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'date'            => $this->date->toDateString(),
            'liturgical_name' => $this->liturgical_name,
            'prayer_cycle'    => $this->prayer_cycle,
            'notes'           => $this->notes,
            'is_public'       => $this->is_public,
            'parts'           => MassPartResource::collection($this->whenLoaded('parts')),
            'role_assignments' => $this->whenLoaded(
                'roleAssignments',
                fn () => $this->roleAssignments->map(fn ($a) => [
                    'user'  => $a->user?->last_name,
                    'role'  => $a->massRole?->name,
                    'notes' => $a->notes,
                ]),
            ),
            'created_at'      => $this->created_at->toIso8601String(),
        ];
    }
}
