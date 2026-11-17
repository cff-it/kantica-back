<?php

namespace App\DTOs\Member;

// Contact en cas d'urgence : un seul bloc de saisie (liens, noms et numéros en texte libre pour une ou plusieurs personnes)
class MemberEmergencyContactDTO
{
    /**
     * @param  list<string>  $relationships  Parents, Famille, Tuteur/Tutrice, Ami(e) proche...
     */
    public function __construct(
        public readonly array $relationships,
        public readonly string $name,
        public readonly string $phone,
    ) {}
}
