<?php

namespace App\Http\Requests\User;

use App\DTOs\User\ListUsersImportsDTO;
use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;

class ListUsersImportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Borne haute : au-delà de PHP_INT_MAX / 20, le décalage de la page n'est plus un entier et sa conversion
            // donne une page arbitraire (les premiers imports pour PHP_INT_MAX)
            'page' => ['nullable', 'integer', 'min:1', 'max:100000',],
        ];
    }

    public function messages(): array
    {
        return [
            'page.integer' => 'La page doit être un nombre entier.',
            'page.min'     => 'La page doit être supérieure ou égale à 1.',
            'page.max'     => 'La page demandée est trop grande.',
        ];
    }

    public function toDTO(): ListUsersImportsDTO
    {
        return new ListUsersImportsDTO(
            viewerId: $this->user()->id,
            viewerIsAdmin: $this->user()->hasRole(RoleName::Admin->value),
            page: max(1, $this->integer('page', 1)),
        );
    }
}
