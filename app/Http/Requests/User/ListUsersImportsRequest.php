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
            'page' => ['nullable', 'integer', 'min:1',],
        ];
    }

    public function messages(): array
    {
        return [
            'page.integer' => 'La page doit être un nombre entier.',
            'page.min'     => 'La page doit être supérieure ou égale à 1.',
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
