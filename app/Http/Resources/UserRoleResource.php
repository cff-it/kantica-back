<?php

namespace App\Http\Resources;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de « Gestion des rôles » (US-25) : identité, email et rôle du compte. Les prénoms et les rôles doivent être
 * chargés.
 *
 * @mixin User
 */
class UserRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'mail'       => $this->mail,
            'last_name'  => $this->last_name,
            'first_name' => $this->firstnames->isEmpty() ? null : $this->firstnames->pluck('first_name')->implode(' '),
            'status'     => $this->status?->value,
            // Un seul rôle par compte ; null pour un compte sans rôle (créé avant KAN-35)
            'role'       => RoleName::highestOf($this->roles->pluck('name'))?->value,
        ];
    }
}
