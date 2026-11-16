<?php

namespace App\Actions\User;

use App\DTOs\User\ImportUsersDTO;
use App\DTOs\User\UserImportIssueDTO;
use App\DTOs\User\UserImportRowDTO;
use App\DTOs\User\UsersImportReportDTO;
use App\Enums\RoleName;
use App\Enums\SacramentType;
use App\Repositories\MemberProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Import partiel : les lignes valides sont créées, les lignes invalides et les comptes déjà existants sont rapportés
readonly class ImportUsersAction
{
    public function __construct(
        private AnalyzeUsersImportAction $analyze,
        private UserRepository $users,
        private MemberProfileRepository $profiles,
        private VoiceRepository $voices,
        private StoreMemberPhotosAction $photos,
    ) {}

    public function __invoke(ImportUsersDTO $dto): UsersImportReportDTO
    {
        $analysis = ($this->analyze)($dto);
        $groups = $this->voices->groupIdsByAbbreviation();

        // Mot de passe aléatoire que personne ne connaît : le membre en choisira un autre.
        // Haché une seule fois : bcrypt est volontairement lent et multiplié par 500 lignes il dépasserait le temps de la requête.
        $password = Hash::make(Str::random(64));

        $created = 0;
        $warnings = [];
        $photoLinks = [];
        $photoRows = [];
        $skipped = $analysis->skipped;

        foreach ($analysis->ready as $row) {
            try {
                // Une transaction par ligne : un échec n'annule pas les comptes déjà créés
                $userId = DB::transaction(fn (): int => $this->createMember($row, $groups[$row->voice], $password));
                $created++;
                $warnings = [...$warnings, ...$row->warningIssues(),];

                if ($row->pictureUrl !== null) {
                    $photoLinks[$userId] = $row->pictureUrl;
                    $photoRows[$userId] = $row;
                }
            } catch (UniqueConstraintViolationException) {
                // Compte créé par quelqu'un d'autre entre l'analyse et l'écriture
                $skipped[] = new UserImportIssueDTO($row->line, $row->displayName(), 'Un compte existe déjà avec cet email.');
            }
        }

        // Les photos sont téléchargées une fois les comptes créés : un échec ne coûte que la photo
        if ($photoLinks !== []) {
            foreach (($this->photos)($photoLinks) as $userId => $photo) {
                if ($photo['url'] !== null) {
                    $this->profiles->updatePicture($userId, $photo['url']);
                } else {
                    $warnings[] = new UserImportIssueDTO($photoRows[$userId]->line, $photoRows[$userId]->displayName(), (string) $photo['warning']);
                }
            }
        }

        usort($skipped, fn (UserImportIssueDTO $a, UserImportIssueDTO $b): int => $a->line <=> $b->line);
        usort($warnings, fn (UserImportIssueDTO $a, UserImportIssueDTO $b): int => $a->line <=> $b->line);

        return new UsersImportReportDTO(
            total: $analysis->total,
            created: $created,
            skipped: $skipped,
            errors: $analysis->errors,
            warnings: $warnings,
            ignoredColumns: $analysis->ignoredColumns,
        );
    }

    // Retourne l'identifiant du compte créé
    private function createMember(UserImportRowDTO $row, int $voiceGroupId, string $password): int
    {
        $user = $this->users->createActive([
            'mail'      => $row->mail,
            'last_name' => $row->lastName,
            'password'  => $password,
        ]);

        $user->assignRole(RoleName::Member->value);

        $this->profiles->upsertDefaultFirstname($user, $row->firstName);
        $this->profiles->upsertProfile($user, [
            'voice_group_id'             => $voiceGroupId,
            'voice_id'                   => null,
            'phone'                      => $row->phone,
            'address'                    => $row->address,
            'birth_date'                 => $row->birthDate,
            'birth_place'                => $row->birthPlace,
            'region_registration_number' => $row->registrationNumber,
            'social_pseudo'              => $row->socialPseudo,
        ]);

        if ($row->hasEngagement()) {
            $this->profiles->upsertEngagement($user, [
                'adhesion_date'    => $row->adhesionDate,
                'engagement_date'  => $row->engagementDate,
                'engagement_place' => $row->engagementPlace,
            ]);
        }

        foreach ($row->sacraments as $type => $sacrament) {
            $this->profiles->upsertSacrament($user, SacramentType::from($type), $sacrament);
        }

        if ($row->hasMarriage()) {
            $this->profiles->upsertMarriage($user, [
                'spouse_name'      => $row->spouseName,
                'spouse_is_member' => $row->spouseIsMember,
                'date'             => $row->marriageDate,
                'place'            => $row->marriagePlace,
            ]);
        }

        if ($row->emergencyName !== null) {
            $this->profiles->addEmergencyContact($user, $row->emergencyName, $row->emergencyPhone, $row->emergencyRelationship);
        }

        return $user->id;
    }
}
