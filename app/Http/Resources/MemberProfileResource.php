<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'last_name'    => $this->last_name,
            'first_name'   => $this->firstnames->firstWhere('default', true)?->first_name,
            'voice_group'  => $this->profile?->voiceGroup?->abbreviation,
            'voice'        => $this->profile?->voice?->name,
            'phone'        => $this->profile?->phone,
            // L'email de contact est l'email du compte (lecture seule sur la fiche)
            'contact_mail' => $this->mail,
            'completed'    => $this->profile !== null,
        ];
    }
}
