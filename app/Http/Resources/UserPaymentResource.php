<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'uuid'             => $this->uuid,
            'user'             => UserResource::make($this->whenLoaded('user')),
            'event_payment'    => EventPaymentResource::make($this->whenLoaded('eventPayment')),
            'paid_amount'      => $this->paid_amount,
            'amount_due'       => $this->amount_due,
            'comment'          => $this->comment,
            'is_cancelled'     => $this->is_cancelled,
            'is_done'          => $this->is_done,
        ];
    }
}
