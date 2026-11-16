<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Ligne de la liste des membres (US-08) : identité, voix et statut du compte (badge « En attente »).
 *
 * @mixin User
 */
class MemberListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Nom, prénom et voix suivent le format de la fiche : une seule définition à maintenir
        $profile = MemberProfileResource::make($this->resource)->resolve($request);

        return [
            'id'     => $this->id,
            ...Arr::only($profile, ['last_name', 'first_name', 'voice_group', 'voice',]),
            'status' => $this->status?->value,
        ];
    }
}
