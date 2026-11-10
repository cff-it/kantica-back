<?php

namespace Database\Seeders;

use App\Enums\RoleName;

/**
 * Jeu des scénarios P1 à P4 : 26 membres (la secrétaire et « Nom1 » à « Nom25 », tous sopranos), soit une page de 20
 * et une page de 6 ; la recherche « nom » en garde 25.
 */
class MemberPaginationSeeder extends MemberScenarioSeeder
{
    private const int NAMED_MEMBERS = 25;

    protected function members(): void
    {
        $this->member('secretaire', 'Ratsimba', 'Sarah', 'A', role: RoleName::Secretary);

        foreach (range(1, self::NAMED_MEMBERS) as $number) {
            $this->member("nom{$number}", "Nom{$number}", 'Prénom', 'S');
        }
    }
}
