<?php

namespace App\Actions\User;

use App\DTOs\User\AssignRoleDTO;
use App\Enums\RoleName;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class AssignRoleAction
{
    public function __construct(private UserRepository $users) {}

    // Remplace le rôle d'un compte (un seul rôle par compte, US-25). Deux garde-fous : le compte administrateur originel
    // garde son rôle, et le dernier admin ne peut pas perdre le sien.
    public function __invoke(AssignRoleDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $user = $this->users->findWithRolesById($dto->userId);

            if ($this->users->isSuperAdmin($user) && $dto->role !== RoleName::Admin) {
                throw ValidationException::withMessages([
                    'role' => 'Le compte administrateur originel de la chorale garde son rôle Admin.',
                ]);
            }

            // Verrou sur le rôle Admin : deux admins qui se retirent le rôle en même temps ne laissent pas zéro admin
            if ($user->hasRole(RoleName::Admin->value) && $dto->role !== RoleName::Admin
                && $this->users->countAdminsWithLock() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Impossible de retirer le rôle Admin : c\'est le dernier administrateur.',
                ]);
            }

            $this->users->syncRole($user, $dto->role);

            return $this->users->findWithRolesById($dto->userId);
        });
    }
}
