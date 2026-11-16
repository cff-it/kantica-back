<?php

namespace Database\Seeders;

use App\Enums\RoleName;

/**
 * Jeu des scénarios C1 et C2 : 5 membres, dont 2 sopranos, 1 alto (la secrétaire), 1 ténor et 1 sans fiche.
 */
class MemberCountsSeeder extends MemberScenarioSeeder
{
    protected function members(): void
    {
        $this->member('secretaire', 'Ratsimba', 'Sarah', 'A', role: RoleName::Secretary);
        $this->member('zafy.lova', 'Zafy', 'Lova', 'T');
        $this->member('rabe.soa', 'Rabe', 'Soa', 'S');
        $this->member('rado.tiana', 'Rado', 'Tiana', 'S');
        $this->member('aaa.paul', 'Aaa', 'Paul', null);
    }
}
