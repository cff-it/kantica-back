<?php

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\RegisterUserDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mail'     => ['required', 'string', 'email', 'max:255', 'unique:users,mail',],
            'password' => ['required', 'string', 'confirmed', Password::defaults(),],
        ];
    }

    public function messages(): array
    {
        return [
            'mail.required'      => 'L\'adresse email est obligatoire.',
            'mail.email'         => 'L\'adresse email n\'est pas valide.',
            'mail.max'           => 'L\'adresse email ne doit pas dépasser 255 caractères.',
            'mail.unique'        => 'Cette adresse email est déjà utilisée.',
            'password.required'  => 'Le mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.password.mixed'   => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
            'password.password.letters' => 'Le mot de passe doit contenir au moins une lettre.',
            'password.password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
            'password.password.symbols' => 'Le mot de passe doit contenir au moins un caractère spécial.',
        ];
    }

    public function toDTO(): RegisterUserDTO
    {
        return new RegisterUserDTO(
            mail: $this->input('mail'),
            password: $this->input('password'),
        );
    }
}
