<?php

namespace App\Actions\Member;

use App\DTOs\Member\CreateMemberDTO;
use App\Enums\RoleName;
use App\Mail\MemberInvitationMail;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

readonly class CreateMemberAction
{
    public function __construct(
        private UserRepository $users,
        private VoiceRepository $voices,
        private SaveMemberRecordAction $saveRecord,
    ) {}

    // Crée le compte (directement actif, rôle membre) avec sa fiche de recensement complète, puis invite le membre par
    // mail avec le mot de passe généré. La fiche est écrite comme à la modification (SaveMemberRecordAction).
    public function __invoke(CreateMemberDTO $dto): User
    {
        $record = $dto->record;
        $voiceGroup = $this->voices->findGroupByAbbreviation($record->voiceGroup);
        $voice = $record->voice !== null ? $this->voices->findVoiceInGroup($voiceGroup, $record->voice) : null;

        // Sans symbole : le mot de passe est lu dans un mail puis recopié, et il n'est écrit nulle part en clair
        $password = Str::password(16, symbols: false);

        try {
            $memberId = DB::transaction(function () use ($dto, $voiceGroup, $voice, $password): int {
                $member = $this->users->createActive([
                    'mail'      => $dto->mail,
                    'last_name' => $dto->record->lastName,
                    'password'  => Hash::make($password),
                ]);

                $member->assignRole(RoleName::Member->value);

                ($this->saveRecord)($member, $dto->record, $voiceGroup->id, $voice?->id);

                return $member->id;
            });
        } catch (UniqueConstraintViolationException) {
            // Compte créé par quelqu'un d'autre entre la validation et l'écriture
            throw ValidationException::withMessages(['mail' => 'Cette adresse email est déjà utilisée.',]);
        }

        // Envoyé une fois le compte enregistré : un échec en base n'invite personne
        Mail::to($dto->mail)->send(new MemberInvitationMail($record->firstName, $dto->mail, $password));

        return $this->users->findRecordById($memberId);
    }
}
