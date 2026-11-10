<?php

namespace App\Actions\Auth;

use App\DTOs\Auth\RegisteredUserDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

readonly class RegisterUserAction
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function __invoke(RegisterUserDTO $dto): RegisteredUserDTO
    {
        $registered = DB::transaction(function () use ($dto): RegisteredUserDTO {
            $user = $this->users->create([
                'mail'     => $dto->mail,
                'password' => Hash::make($dto->password),
                'status'   => UserStatus::Pending,
            ]);

            $user->assignRole(RoleName::Member->value);

            $token = $user->createToken('api')->plainTextToken;

            return new RegisteredUserDTO(
                user: $user->load('roles'),
                token: $token,
            );
        });

        // Déclenche l'envoi de l'email de vérification (listener natif Laravel)
        event(new Registered($registered->user));

        return $registered;
    }
}
