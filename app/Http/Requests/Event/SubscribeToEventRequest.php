<?php

namespace App\Http\Requests\Event;

use App\DTOs\Event\SubscribeToEventDTO;
use Illuminate\Foundation\Http\FormRequest;

class SubscribeToEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function toDTO(): SubscribeToEventDTO
    {
        return new SubscribeToEventDTO(
            eventId: (int) $this->route('event'),
            userId: $this->integer('user_id'),
            subscribedAt: now(),
        );
    }
}
