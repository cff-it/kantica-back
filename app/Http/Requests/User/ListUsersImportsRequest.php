<?php

namespace App\Http\Requests\User;

use App\DTOs\User\ListUsersImportsDTO;
use App\Enums\RoleName;
use App\Http\Requests\Concerns\ValidatesPage;
use Illuminate\Foundation\Http\FormRequest;

class ListUsersImportsRequest extends FormRequest
{
    use ValidatesPage;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => $this->pageRules(),
        ];
    }

    public function messages(): array
    {
        return $this->pageMessages();
    }

    public function toDTO(): ListUsersImportsDTO
    {
        return new ListUsersImportsDTO(
            viewerId: $this->user()->id,
            viewerIsAdmin: $this->user()->hasRole(RoleName::Admin->value),
            page: $this->requestedPage(),
        );
    }
}
