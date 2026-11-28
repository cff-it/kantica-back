<?php

namespace App\Http\Resources;

use App\DTOs\Event\AttendanceOverviewDTO;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Écran « Présences » (choix de l'événement) : trois groupes, leurs décomptes pour les pastilles, et la pagination de
 * « Déjà saisis », seul groupe paginé.
 *
 * @property AttendanceOverviewDTO $resource
 */
class AttendanceOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'to_record' => $this->resource->toRecord->map(fn (Event $event) => $this->item($event))->all(),
            'upcoming'  => $this->resource->upcoming->map(fn (Event $event) => $this->item($event))->all(),
            'recorded'  => $this->resource->recorded
                ->map(fn (Event $event) => [
                    ...$this->item($event),
                    // « 8 / 9 présents » : présences de la liste de saisie sur son effectif
                    'present_count' => $event->present_count,
                    'total'         => $this->resource->attendeesCount,
                ])
                ->all(),
        ];
    }

    public function with(Request $request): array
    {
        $counts = $this->resource->counts;

        return [
            'meta' => [
                'counts'    => $counts,
                'page'      => $this->resource->page,
                'per_page'  => $this->resource->perPage,
                'last_page' => max(1, (int) ceil($counts['recorded'] / $this->resource->perPage)),
            ],
        ];
    }

    private function item(Event $event): array
    {
        return [
            'id'         => $event->id,
            'name'       => $event->name,
            'type'       => $event->type->value,
            'date_start' => $event->date_start->toIso8601String(),
            'location'   => $event->location,
        ];
    }
}
