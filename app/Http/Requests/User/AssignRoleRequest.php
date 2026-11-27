<?php

namespace App\Http\Requests\User;

use App\DTOs\User\AssignRoleDTO;
use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rôle d'un compte, choisi par l'admin dans « Gestion des rôles » (US-25) : un seul rôle par compte.
 */
class AssignRoleRequest extends FormRequest
{
    // L'accès (Admin) est contrôlé par la Policy sur la route
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(RoleName::class),],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Le rôle est obligatoire.',
            'role.enum'     => 'Le rôle doit être admin, secretaire ou membre.',
        ];
    }

    public function toDTO(): AssignRoleDTO
    {
        return new AssignRoleDTO(
            userId: (int) $this->route('user'),
            role: RoleName::from($this->input('role')),
        );
    }
}
