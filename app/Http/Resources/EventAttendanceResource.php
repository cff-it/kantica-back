<?php

namespace App\Http\Resources;

use App\DTOs\Event\EventAttendanceListDTO;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Écran « Saisir les présences » (US-11) : l'événement (date et nom), le compteur « 5 / 9 présents » et un membre par
 * ligne avec sa voix et sa case cochée.
 *
 * @property EventAttendanceListDTO $resource
 */
class EventAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $present = array_flip($this->resource->presentIds);

        return [
            'event'         => [
                'id'         => $this->resource->event->id,
                'name'       => $this->resource->event->name,
                'date_start' => $this->resource->event->date_start->toIso8601String(),
            ],
            'present_count' => count($present),
            'total_count'   => $this->resource->members->count(),
            'members'       => $this->resource->members
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    // Identité et voix suivent le format de la fiche : une seule définition à maintenir
                    ...Arr::only(
                        MemberProfileResource::make($member)->resolve($request),
                        ['last_name', 'first_name', 'voice_group', 'voice',],
                    ),
                    'present' => isset($present[$member->id]),
                ])
                ->all(),
        ];
    }
}
