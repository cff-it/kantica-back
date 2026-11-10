<?php

namespace App\Actions\Member;

use App\DTOs\Member\MemberProfileDTO;
use App\Models\User;
use App\Repositories\MemberProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Support\Facades\DB;

readonly class UpdateMemberProfileAction
{
    public function __construct(
        private UserRepository $users,
        private MemberProfileRepository $profiles,
        private VoiceRepository $voices,
    ) {}

    // Met à jour la fiche d'un autre membre (créée si elle n'existe pas encore). Aucune règle de voix : la secrétaire
    // et l'admin peuvent la changer. L'email et le statut du compte ne sont jamais modifiés ici.
    public function __invoke(int $memberId, MemberProfileDTO $dto): User
    {
        $member = $this->users->findById($memberId);
        $voiceGroup = $this->voices->findGroupByAbbreviation($dto->voiceGroup);
        $voice = $dto->voice !== null ? $this->voices->findVoiceInGroup($voiceGroup, $dto->voice) : null;

        DB::transaction(function () use ($member, $dto, $voiceGroup, $voice): void {
            $this->profiles->updateLastName($member, $dto->lastName);
            $this->profiles->upsertDefaultFirstname($member, $dto->firstName);
            $this->profiles->upsertProfile($member, [
                'voice_group_id' => $voiceGroup->id,
                'voice_id'       => $voice?->id,
                'phone'          => $dto->phone,
            ]);
        });

        // Rechargé depuis la base : les relations déjà chargées sur $member sont périmées
        return $this->users->findById($memberId);
    }
}
