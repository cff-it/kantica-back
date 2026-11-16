<?php

namespace App\Http\Requests\Event;

use App\DTOs\Event\RegisterMemberDTO;
use Illuminate\Foundation\Http\FormRequest;

class RegisterMemberRequest extends FormRequest
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

    public function toDTO(): RegisterMemberDTO
    {
        return new RegisterMemberDTO(
            eventId: (int) $this->route('event'),
            userId: $this->integer('user_id'),
        );
    }
}
