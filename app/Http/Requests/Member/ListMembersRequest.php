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
            // Borne haute : au-delà de PHP_INT_MAX / 20, le décalage de la page n'est plus un entier (erreur 500)
            'page'   => ['nullable', 'integer', 'min:1', 'max:100000',],
        ];
    }

    public function messages(): array
    {
        return [
            'voice.enum' => 'Le groupe de voix doit être S, A, T ou B.',
            'page.min'   => 'La page doit être supérieure ou égale à 1.',
            'page.max'   => 'La page demandée est trop grande.',
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
