<?php

namespace Database\Seeders;

use App\Actions\Deploy\SyncRolesAction;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(SyncRolesAction $syncRoles): void
    {
        $syncRoles();
    }
}
