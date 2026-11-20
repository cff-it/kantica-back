<?php

namespace App\DTOs\Member;

// Nouveau membre ajouté par la secrétaire ou l'admin : l'email du compte et sa fiche de recensement complète
class CreateMemberDTO
{
    public function __construct(
        public readonly string $mail,
        public readonly MemberRecordDTO $record,
    ) {}
}
