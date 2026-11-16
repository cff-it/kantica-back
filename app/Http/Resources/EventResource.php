<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'date_start'     => $this->date_start->toIso8601String(),
            'date_end'       => $this->date_end?->toIso8601String(),
            'need_payment'   => $this->need_payment,
            'payment_amount' => $this->payment_amount,
            'subscriptions'  => $this->whenLoaded(
                'subscriptions',
                fn () => $this->subscriptions->map(fn ($s) => [
                    'user_id'       => $s->user_id,
                    'subscribed_at' => $s->subscribed_at->toIso8601String(),
                    'has_paid'      => $s->has_paid,
                ]),
            ),
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
