<?php

namespace App\Actions\Member;

use App\DTOs\Member\AssignVoiceDTO;
use App\Models\User;
use App\Repositories\MemberProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Validation\ValidationException;

readonly class AssignVoiceAction
{
    public function __construct(
        private UserRepository $users,
        private VoiceRepository $voices,
        private MemberProfileRepository $profiles,
    ) {}

    // Remplace le groupe de voix d'un membre (une seule voix par membre). La voix précise (A2...) n'appartient qu'à
    // l'ancien groupe : elle est effacée quand le groupe change, gardée sinon. Le reste de la fiche n'est pas touché.
    public function __invoke(AssignVoiceDTO $dto): User
    {
        $member = $this->users->findRecordById($dto->memberId);

        // La fiche exige un groupe de voix et des champs obligatoires : on ne la crée pas à partir de la seule voix
        if ($member->profile === null) {
            throw ValidationException::withMessages([
                'voice' => 'Ce membre n\'a pas encore de fiche de recensement : utilisez « Modifier » pour la remplir.',
            ]);
        }

        $voiceGroup = $this->voices->findGroupByAbbreviation($dto->voice->value);

        if ($member->profile->voice_group_id !== $voiceGroup->id) {
            $this->profiles->updateVoice($member->profile, $voiceGroup->id, null);
        }

        // Rechargé depuis la base : les relations déjà chargées sur $member sont périmées
        return $this->users->findRecordById($dto->memberId);
    }
}
