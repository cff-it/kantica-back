<?php

namespace App\Actions\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

readonly class LoginAction
{
    public function __invoke(LoginDTO $dto): string
    {
        $user = User::where('mail', $dto->mail)->first();

        if (!$user || !Hash::check($dto->password, $user->password)) {
            throw new AuthenticationException('Identifiants invalides.');
        }

        return $user->createToken('api')->plainTextToken;
    }
}
