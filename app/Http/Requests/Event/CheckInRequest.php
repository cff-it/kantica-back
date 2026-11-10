<?php

namespace App\Http\Requests\Event;

use App\DTOs\Event\CheckInDTO;
use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
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

    public function toDTO(): CheckInDTO
    {
        return new CheckInDTO(
            eventId: (int) $this->route('event'),
            userId: $this->integer('user_id'),
            dateIn: now(),
        );
    }
}
