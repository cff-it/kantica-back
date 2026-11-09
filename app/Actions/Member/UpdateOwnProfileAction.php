<?php

namespace App\Actions\Member;

use App\DTOs\Member\MemberProfileDTO;
use App\Models\User;
use App\Models\Voice;
use App\Models\VoiceGroup;
use App\Repositories\MemberProfileRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class UpdateOwnProfileAction
{
    public function __construct(
        private MemberProfileRepository $profiles,
        private VoiceRepository $voices,
    ) {}

    // Crée la fiche si elle n'existe pas, sinon la met à jour (upsert sur l'utilisateur courant)
    public function __invoke(User $user, MemberProfileDTO $dto): User
    {
        $attributes = ['phone' => $dto->phone,];

        if ($dto->voiceGroup !== null) {
            $voiceGroup = $this->voices->findGroupByAbbreviation($dto->voiceGroup);
            $voice = $dto->voice !== null ? $this->voices->findVoiceInGroup($voiceGroup, $dto->voice) : null;

            // Voix modifiable : on l'enregistre. Sinon elle doit être identique à celle de la fiche (voir UserPolicy)
            if ($user->can('updateOwnVoice', User::class)) {
                $attributes['voice_group_id'] = $voiceGroup->id;
                $attributes['voice_id'] = $voice?->id;
            } else {
                $this->ensureVoiceIsUnchanged($user, $voiceGroup, $voice);
            }
        }

        DB::transaction(function () use ($user, $dto, $attributes): void {
            $this->profiles->updateLastName($user, $dto->lastName);
            $this->profiles->replaceFirstnames($user, $this->splitFirstName($dto->firstName));
            $this->profiles->upsertProfile($user, $attributes);
        });

        return $this->profiles->load($user);
    }

    /**
     * Chaque mot du prénom saisi est un prénom à part entière ("Hanta Rasoa" donne deux lignes).
     * Les espaces en trop ne créent pas de prénom vide.
     *
     * @return list<string>
     */
    private function splitFirstName(string $firstName): array
    {
        return array_values(array_filter(explode(' ', $firstName), fn (string $word) => $word !== ''));
    }

    // Renvoyer la voix déjà enregistrée reste permis (le formulaire est pré-rempli avec la fiche)
    private function ensureVoiceIsUnchanged(User $user, VoiceGroup $voiceGroup, ?Voice $voice): void
    {
        $profile = $user->profile;

        if ($profile->voice_group_id === $voiceGroup->id && $profile->voice_id === $voice?->id) {
            return;
        }

        throw ValidationException::withMessages([
            'voice_group' => 'Votre voix ne peut être modifiée que par un administrateur.',
        ]);
    }
}
