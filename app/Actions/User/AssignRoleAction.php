<?php

namespace App\Actions\User;

use App\DTOs\User\AssignRoleDTO;
use App\Models\User;
use App\Repositories\UserRepository;

readonly class AssignRoleAction
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(AssignRoleDTO $dto): User
    {
        $user = $this->users->findById($dto->userId);
        $user->assignRole($dto->role);

        return $user->load('roles');
    }
}
