<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'user'          => UserResource::make($this->whenLoaded('user')),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}
