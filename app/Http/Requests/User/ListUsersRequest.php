<?php

namespace App\Http\Requests\User;

use App\DTOs\User\ListUsersDTO;
use App\Http\Requests\Concerns\ValidatesPage;
use Illuminate\Foundation\Http\FormRequest;

class ListUsersRequest extends FormRequest
{
    use ValidatesPage;

    // L'accès (Admin) est contrôlé par la Policy sur la route
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100',],
            'page'   => $this->pageRules(),
        ];
    }

    public function messages(): array
    {
        return $this->pageMessages();
    }

    public function toDTO(): ListUsersDTO
    {
        return new ListUsersDTO(
            search: $this->input('search'),
            page: $this->requestedPage(),
        );
    }
}
