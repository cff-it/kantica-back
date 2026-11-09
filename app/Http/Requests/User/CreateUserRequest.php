<?php

namespace App\Http\Requests\User;

use App\DTOs\User\CreateUserDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mail'      => ['required', 'email', 'unique:users,mail'],
            'last_name' => ['required', 'string', 'max:100'],
            'nick_name' => ['nullable', 'string', 'max:100'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toDTO(): CreateUserDTO
    {
        return new CreateUserDTO(
            mail: $this->input('mail'),
            lastName: $this->input('last_name'),
            password: $this->input('password'),
            nickName: $this->input('nick_name'),
        );
    }
}
