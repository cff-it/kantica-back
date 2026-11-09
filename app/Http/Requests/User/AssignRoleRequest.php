<?php

namespace App\Http\Requests\User;

use App\DTOs\User\AssignRoleDTO;
use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'exists:roles,name'],
        ];
    }

    public function toDTO(): AssignRoleDTO
    {
        return new AssignRoleDTO(
            userId: (int) $this->route('user'),
            role: $this->input('role'),
        );
    }
}
