<?php

namespace App\Actions\Member;

use App\DTOs\Member\MemberProfileDTO;
use App\Models\User;
use App\Repositories\MemberProfileRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Support\Facades\DB;

readonly class UpdateOwnProfileAction
{
    public function __construct(
        private MemberProfileRepository $profiles,
        private VoiceRepository $voices,
    ) {}

    // Crée la fiche si elle n'existe pas, sinon la met à jour (upsert sur l'utilisateur courant)
    public function __invoke(User $user, MemberProfileDTO $dto): User
    {
        $voiceGroup = $this->voices->findGroupByAbbreviation($dto->voiceGroup);
        $voice = $dto->voice !== null ? $this->voices->findVoiceInGroup($voiceGroup, $dto->voice) : null;

        DB::transaction(function () use ($user, $dto, $voiceGroup, $voice): void {
            $this->profiles->updateLastName($user, $dto->lastName);
            $this->profiles->upsertDefaultFirstname($user, $dto->firstName);
            $this->profiles->upsertProfile($user, [
                'voice_group_id' => $voiceGroup->id,
                'voice_id'       => $voice?->id,
                'phone'          => $dto->phone,
            ]);
        });

        return $this->profiles->load($user);
    }
}
