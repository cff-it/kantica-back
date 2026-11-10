<?php

namespace App\Actions\User;

use App\DTOs\User\CreateUserDTO;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

readonly class CreateUserAction
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(CreateUserDTO $dto): User
    {
        return $this->users->create([
            'mail'      => $dto->mail,
            'last_name' => $dto->lastName,
            'nick_name' => $dto->nickName,
            'password'  => Hash::make($dto->password),
        ]);
    }
}
