<?php

namespace App\Http\Requests\Member;

use App\DTOs\Member\MemberRecordDTO;

/**
 * Modification de la fiche de recensement complète d'un membre par la secrétaire ou l'admin (US-08, écran
 * « Modifier la fiche »). Champs et validations : voir MemberRecordRequest.
 *
 * Le PUT est complet : les champs obligatoires de la maquette le sont ici, et un champ facultatif absent ou vide est
 * effacé (mariage, engagement, pseudo, matricule). Seule exception, la voix précise (S1, A2...) que la maquette ne
 * propose pas : sans clé `voice`, elle reste inchangée tant que le groupe de voix ne change pas.
 *
 * L'email est celui du compte (identifiant de connexion) : il n'est pas modifiable ici. La photo non plus.
 */
class UpdateMemberProfileRequest extends MemberRecordRequest
{
    public function toDTO(): MemberRecordDTO
    {
        return $this->record();
    }
}
