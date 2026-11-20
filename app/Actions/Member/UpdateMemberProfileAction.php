<?php

namespace App\Actions\Member;

use App\DTOs\Member\MemberRecordDTO;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Support\Facades\DB;

readonly class UpdateMemberProfileAction
{
    public function __construct(
        private UserRepository $users,
        private VoiceRepository $voices,
        private SaveMemberRecordAction $saveRecord,
    ) {}

    // Met à jour la fiche de recensement complète d'un autre membre (créée si elle n'existe pas encore). Aucune règle de
    // voix : la secrétaire et l'admin peuvent la changer. L'email, le statut et la photo ne sont jamais modifiés ici.
    public function __invoke(int $memberId, MemberRecordDTO $dto): User
    {
        $member = $this->users->findRecordById($memberId);
        $voiceGroup = $this->voices->findGroupByAbbreviation($dto->voiceGroup);
        $voice = $dto->voice !== null ? $this->voices->findVoiceInGroup($voiceGroup, $dto->voice) : null;

        // Sans clé `voice`, la voix précise reste, sauf si le groupe change : l'ancienne voix n'en ferait plus partie
        $keepVoice = !$dto->voiceProvided && $member->profile?->voice_group_id === $voiceGroup->id;
        $voiceId = $keepVoice ? $member->profile->voice_id : $voice?->id;

        DB::transaction(fn () => ($this->saveRecord)($member, $dto, $voiceGroup->id, $voiceId));

        // Rechargé depuis la base : les relations déjà chargées sur $member sont périmées
        return $this->users->findRecordById($memberId);
    }
}
