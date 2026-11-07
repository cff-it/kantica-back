<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'mail'           => $this->mail,
            'last_name'      => $this->last_name,
            'nick_name'      => $this->nick_name,
            'status'         => $this->status?->value,
            'email_verified' => $this->email_verified_at !== null,
            'firstnames' => $this->whenLoaded('firstnames', fn () => $this->firstnames->map(fn ($f) => [
                'first_name' => $f->first_name,
                'order'      => $f->order,
                'default'    => $f->default,
            ])),
            'roles'      => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            // Fiche de recensement (même format que GET /api/me/profile)
            'profile'    => $this->when(
                $this->relationLoaded('profile') && $this->relationLoaded('firstnames'),
                fn () => MemberProfileResource::make($this->resource),
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
