<?php

namespace App\Actions\Deploy;

use App\Enums\RoleName;
use Spatie\Permission\Models\Role;

readonly class SyncRolesAction
{
    // Idempotent : ne crée que les rôles manquants, retourne le nombre de rôles créés
    public function __invoke(): int
    {
        $created = 0;

        foreach (RoleName::cases() as $role) {
            $created += (int) Role::findOrCreate($role->value, 'web')->wasRecentlyCreated;
        }

        return $created;
    }
}
