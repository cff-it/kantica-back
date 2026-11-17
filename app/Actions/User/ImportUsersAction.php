<?php

namespace App\Actions\User;

use App\DTOs\User\ImportUsersDTO;
use App\DTOs\User\UserImportIssueDTO;
use App\DTOs\User\UserImportRowDTO;
use App\DTOs\User\UsersImportReportDTO;
use App\Enums\DatePrecision;
use App\Enums\RoleName;
use App\Enums\SacramentType;
use App\Models\UserImport;
use App\Repositories\MemberProfileRepository;
use App\Repositories\UserImportRepository;
use App\Repositories\UserRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Import partiel : les lignes valides sont créées, les lignes invalides et les comptes déjà existants sont rapportés.
// Fait avancer le suivi (`UserImport`) à chaque compte créé et à chaque lot de photos : une étape par compte, plus une
// par photo à télécharger. Appelée par `RunUsersImportAction`, depuis `ImportUsersJob`.
readonly class ImportUsersAction
{
    public function __construct(
        private AnalyzeUsersImportAction $analyze,
        private UserRepository $users,
        private MemberProfileRepository $profiles,
        private VoiceRepository $voices,
        private StoreMemberPhotosAction $photos,
        private UserImportRepository $imports,
    ) {}

    public function __invoke(ImportUsersDTO $dto, UserImport $import): UsersImportReportDTO
    {
        $analysis = ($this->analyze)($dto);
        $groups = $this->voices->groupIdsByAbbreviation();

        // Le total est celui de cette analyse : des membres ont pu être créés depuis l'envoi du fichier
        $this->imports->start($import, $analysis->steps());

        // Mot de passe aléatoire que personne ne connaît : le membre en choisira un autre.
        // Haché une seule fois : bcrypt est volontairement lent (environ 0,2 s), inutile de le répéter pour chaque ligne.
        $password = Hash::make(Str::random(64));

        $created = 0;
        $warnings = [];
        $photoLinks = [];
        $photoRows = [];
        $skipped = $analysis->skipped;

        foreach ($analysis->ready as $row) {
            $steps = 1;

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
                // Compte créé par quelqu'un d'autre entre l'analyse et l'écriture : ni compte ni photo à traiter pour cette ligne
                $skipped[] = new UserImportIssueDTO($row->line, $row->displayName(), 'Un compte existe déjà avec cet email.');
                $steps = $row->pictureUrl === null ? 1 : 2;
            }

            $this->imports->advance($import, $steps);
        }

        // Les photos sont téléchargées une fois les comptes créés : un échec ne coûte que la photo.
        // Chaque lot est enregistré et compté dès qu'il est fini, pour que le pourcentage avance pendant les téléchargements.
        if ($photoLinks !== []) {
            ($this->photos)($photoLinks, function (array $results) use ($import, $photoRows, &$warnings): void {
                foreach ($results as $userId => $photo) {
                    if ($photo['url'] !== null) {
                        $this->profiles->updatePicture($userId, $photo['url']);
                    } else {
                        $warnings[] = new UserImportIssueDTO($photoRows[$userId]->line, $photoRows[$userId]->displayName(), (string) $photo['warning']);
                    }
                }

                $this->imports->advance($import, count($results));
            });
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
                'adhesion_date'             => $row->adhesionDate?->date,
                'adhesion_date_precision'   => $row->adhesionDate?->precision ?? DatePrecision::Day,
                'engagement_date'           => $row->engagementDate?->date,
                'engagement_date_precision' => $row->engagementDate?->precision ?? DatePrecision::Day,
                'engagement_place'          => $row->engagementPlace,
            ]);
        }

        foreach ($row->sacraments as $type => $sacrament) {
            $this->profiles->upsertSacrament($user, SacramentType::from($type), [
                'date'           => $sacrament['date']?->date,
                'date_precision' => $sacrament['date']?->precision ?? DatePrecision::Day,
                'place'          => $sacrament['place'],
            ]);
        }

        if ($row->hasMarriage()) {
            $this->profiles->upsertMarriage($user, [
                'spouse_name'      => $row->spouseName,
                'spouse_is_member' => $row->spouseIsMember,
                'date'             => $row->marriageDate?->date,
                'date_precision'   => $row->marriageDate?->precision ?? DatePrecision::Day,
                'place'            => $row->marriagePlace,
            ]);
        }

        if ($row->emergencyName !== null) {
            $this->profiles->addEmergencyContact($user, $row->emergencyName, $row->emergencyPhone, $row->emergencyRelationship);
        }

        return $user->id;
    }
}
