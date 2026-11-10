<?php

namespace App\Http\Requests\Member;

use App\DTOs\Member\ListMembersDTO;
use App\Enums\VoicePart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100',],
            'voice'  => ['nullable', Rule::enum(VoicePart::class),],
            'page'   => ['nullable', 'integer', 'min:1',],
        ];
    }

    public function messages(): array
    {
        return [
            'voice.enum' => 'Le groupe de voix doit être S, A, T ou B.',
            'page.min'   => 'La page doit être supérieure ou égale à 1.',
        ];
    }

    public function toDTO(): ListMembersDTO
    {
        return new ListMembersDTO(
            search: $this->input('search'),
            voice: $this->enum('voice', VoicePart::class),
            page: max(1, $this->integer('page', 1)),
        );
    }
}
