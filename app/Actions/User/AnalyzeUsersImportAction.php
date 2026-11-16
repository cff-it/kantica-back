<?php

namespace App\Actions\User;

use App\DTOs\User\ImportUsersDTO;
use App\DTOs\User\UserImportIssueDTO;
use App\DTOs\User\UserImportRowDTO;
use App\DTOs\User\UsersImportAnalysisDTO;
use App\Enums\VoicePart;
use App\Repositories\UserRepository;
use App\Repositories\VoiceRepository;
use DateTimeInterface;
use ErrorException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

// Lit le fichier Excel et classe chaque ligne (prête, ignorée, en erreur) sans rien écrire en base.
// Sert à l'aperçu et à l'import, qui rejouent la même analyse.
readonly class AnalyzeUsersImportAction
{
    // Limite d'un import : durée du traitement (un compte et une photo par ligne) et lisibilité du rapport
    public const int MAX_ROWS = 500;

    // En-têtes reconnus (sans accents, casse ni parenthèses) et champ correspondant
    // « Nom et prénom(s) », « Adresse e-mail » et « Numéro de téléphone » sont les intitulés du formulaire de recensement
    private const array HEADERS = [
        'nom'               => 'last_name',
        'prenom'            => 'first_name',
        'prenoms'           => 'first_name',
        'nometprenom'       => 'full_name',
        'nometprenoms'      => 'full_name',
        'voix'              => 'voice',
        'telephone'         => 'phone',
        'tel'               => 'phone',
        'numerodetelephone' => 'phone',
        'numerotelephone'   => 'phone',
        'email'             => 'mail',
        'mail'              => 'mail',
        'courriel'          => 'mail',
        'adressemail'       => 'mail',
        'adresseemail'      => 'mail',
        // Autres champs du formulaire de recensement
        'adresse'                                   => 'address',
        'dateetlieudenaissance'                     => 'birth',
        'nmatriculeetquartier'                      => 'registration_number',
        'mariea'                                    => 'spouse',
        'datedumariage'                             => 'marriage_date',
        'lieudumariage'                             => 'marriage_place',
        'datedadhesionalachorale'                   => 'adhesion_date',
        'datedengagement'                           => 'engagement_date',
        'lieudelengagement'                         => 'engagement_place',
        'nomdelapersonneacontacterencasdurgence'    => 'emergency_name',
        'numerodetelephoneacontacterencasdurgence'  => 'emergency_phone',
        'photodidentiterecente'                     => 'picture_url',
        'pseudosurfacebookoumessenger'              => 'social_pseudo',
        'datedubapteme'                             => 'baptism_date',
        'lieudubapteme'                             => 'baptism_place',
        'datedelapremierecommunion'                 => 'first_communion_date',
        'lieudelapremierecommunion'                 => 'first_communion_place',
        'datedelaconfirmation'                      => 'confirmation_date',
        'lieudelaconfirmation'                      => 'confirmation_place',
        'acontacterencasdurgence'                   => 'emergency_relationship',
    ];

    // Colonnes de nom exigées (libellé du modèle de fichier) tant que le fichier n'a pas la colonne unique « Nom et prénom(s) »
    private const array NAME_COLUMNS = [
        'last_name'  => 'Nom',
        'first_name' => 'Prénom',
    ];

    // Autres colonnes obligatoires (le téléphone est facultatif)
    private const array REQUIRED_COLUMNS = [
        'voice' => 'Voix',
        'mail'  => 'Email',
    ];

    public function __construct(
        private UserRepository $users,
        private VoiceRepository $voices,
        private SplitFullNameAction $splitFullName,
        private ParseRecensementFieldsAction $parseRecensement,
        private BuildMemberNameKeyAction $nameKey,
    ) {}

    public function __invoke(ImportUsersDTO $dto): UsersImportAnalysisDTO
    {
        ['rows' => $rows, 'ignored' => $ignoredColumns,] = $this->readRows($dto->path);
        $groups = $this->voices->groupIdsByAbbreviation();
        $rules = $this->rules(array_keys($groups));

        $valid = [];
        $errors = [];
        $lineByMail = [];
        $lineByName = [];

        foreach ($rows as $line => $values) {
            $values = $this->normalize($values, $groups);
            $nameProblem = $values['name_problem'];

            // Un nom impossible à séparer a déjà son message : inutile d'y ajouter « Nom manquant. Prénom manquant. »
            $validator = Validator::make(
                $values,
                $nameProblem === null ? $rules : Arr::except($rules, ['last_name', 'first_name',]),
                $this->messages(),
            );

            if ($validator->fails() || $nameProblem !== null) {
                $errors[] = $this->issue($line, $values, implode(' ', array_filter([$nameProblem, ...$validator->errors()->all(),])));

                continue;
            }

            // Un membre est identifié par son email, ou par son nom et ses prénoms
            $mail = mb_strtolower($values['mail']);
            $nameKey = ($this->nameKey)($values['last_name'], $values['first_name']);

            if (isset($lineByMail[$mail])) {
                $errors[] = $this->issue($line, $values, 'Email déjà présent à la ligne '.$lineByMail[$mail].' du fichier.');

                continue;
            }

            if (isset($lineByName[$nameKey])) {
                $errors[] = $this->issue($line, $values, 'Nom et prénom(s) déjà présents à la ligne '.$lineByName[$nameKey].' du fichier.');

                continue;
            }

            $lineByMail[$mail] = $line;
            $lineByName[$nameKey] = $line;
            $valid[] = new UserImportRowDTO(
                line: $line,
                lastName: $values['last_name'],
                firstName: $values['first_name'],
                voice: $values['voice'],
                phone: $values['phone'],
                mail: $mail,
                address: $values['address'],
                birthDate: $values['birth_date'],
                birthPlace: $values['birth_place'],
                registrationNumber: $values['registration_number'],
                pictureUrl: $values['picture_url'],
                socialPseudo: $values['social_pseudo'],
                adhesionDate: $values['adhesion_date'],
                engagementDate: $values['engagement_date'],
                engagementPlace: $values['engagement_place'],
                sacraments: $values['sacraments'],
                spouseName: $values['spouse_name'],
                spouseIsMember: $values['spouse_is_member'],
                marriageDate: $values['marriage_date'],
                marriagePlace: $values['marriage_place'],
                emergencyName: $values['emergency_name'],
                emergencyPhone: $values['emergency_phone'],
                emergencyRelationship: $values['emergency_relationship'],
                warnings: $values['warnings'],
            );
        }

        $existing = array_flip($this->users->existingMails(array_keys($lineByMail)));
        $existingNames = $this->existingNameKeys();
        $ready = [];
        $skipped = [];

        foreach ($valid as $row) {
            if (isset($existing[$row->mail])) {
                $skipped[] = new UserImportIssueDTO($row->line, $row->displayName(), 'Un compte existe déjà avec cet email.');

                continue;
            }

            if (isset($existingNames[($this->nameKey)($row->lastName, $row->firstName)])) {
                $skipped[] = new UserImportIssueDTO($row->line, $row->displayName(), 'Un membre existe déjà avec ce nom et ces prénoms.');

                continue;
            }

            $ready[] = $row;
        }

        return new UsersImportAnalysisDTO(
            total: count($rows),
            ready: $ready,
            skipped: $skipped,
            errors: $errors,
            warnings: array_merge(...array_map(fn (UserImportRowDTO $row): array => $row->warningIssues(), $ready)),
            ignoredColumns: $ignoredColumns,
        );
    }

    /**
     * Clés nom + prénoms des membres existants : avec le prénom par défaut seul, et avec tous les prénoms à la suite
     * (« Fanja » et « Fanja Marie » pour un membre qui a les prénoms Fanja et Marie).
     *
     * @return array<string, true>
     */
    private function existingNameKeys(): array
    {
        $keys = [];

        foreach ($this->users->memberNames() as $member) {
            foreach (array_filter([$member['default_first_name'], $member['first_names'],]) as $firstName) {
                $keys[($this->nameKey)($member['last_name'], $firstName)] = true;
            }
        }

        return $keys;
    }

    /**
     * Valeurs de chaque ligne non vide de la première feuille, indexées par numéro de ligne Excel.
     *
     * @return array{rows: array<int, array<string, string>>, ignored: list<string>} ignored : en-têtes des colonnes non reprises
     */
    private function readRows(string $path): array
    {
        // Lignes vides conservées : le compteur donne ainsi le vrai numéro de ligne dans Excel
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $columns = null;
        $ignored = [];
        $rows = [];

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = [];

                    foreach ($row->toArray() as $index => $value) {
                        $cells[$index] = $this->cellToString($value);
                    }

                    if ($columns === null) {
                        if (array_filter($cells, fn (string $cell): bool => $cell !== '') !== []) {
                            $columns = $this->mapColumns($cells);
                            // Colonnes que l'import ne reprend pas : le front peut les signaler à la secrétaire
                            $ignored = array_values(array_filter(
                                $cells,
                                fn (string $header, int $index): bool => $header !== '' && !isset($columns[$index]),
                                ARRAY_FILTER_USE_BOTH,
                            ));
                        }

                        continue;
                    }

                    $values = array_fill_keys(array_unique(self::HEADERS), '');

                    foreach ($columns as $index => $field) {
                        $values[$field] = $cells[$index] ?? '';
                    }

                    if (array_filter($values, fn (string $value): bool => $value !== '') === []) {
                        continue;
                    }

                    if (count($rows) >= self::MAX_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => ['Le fichier dépasse '.self::MAX_ROWS.' lignes : découpez-le en plusieurs fichiers.',],
                        ]);
                    }

                    $rows[$line] = $values;
                }

                break;
            }
        } catch (OpenSpoutException|ErrorException) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier est illisible : utilisez un fichier Excel (.xlsx), de préférence le modèle.',],
            ]);
        } finally {
            $reader->close();
        }

        if ($columns === null) {
            throw ValidationException::withMessages(['file' => ['Le fichier est vide.',],]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => ['Le fichier ne contient aucune ligne à importer.',],]);
        }

        return ['rows' => $rows, 'ignored' => $ignored,];
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string> champ de chaque colonne reconnue, indexé par numéro de colonne
     */
    private function mapColumns(array $headers): array
    {
        $columns = [];

        foreach ($headers as $index => $header) {
            $field = self::HEADERS[$this->normalizeHeader($header)] ?? null;

            // En cas de colonne en double, la première l'emporte
            if ($field !== null && !in_array($field, $columns, true)) {
                $columns[$index] = $field;
            }
        }

        // Quand le fichier a à la fois Nom et Prénom, ces deux colonnes l'emportent sur « Nom et prénom(s) »
        if (in_array('last_name', $columns, true) && in_array('first_name', $columns, true)) {
            $columns = array_diff($columns, ['full_name',]);
        }

        $required = in_array('full_name', $columns, true)
            ? self::REQUIRED_COLUMNS
            : [...self::NAME_COLUMNS, ...self::REQUIRED_COLUMNS,];
        $missing = array_diff_key($required, array_flip($columns));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => ['Colonnes manquantes : '.implode(', ', $missing).'. Utilisez le modèle de fichier.',],
            ]);
        }

        return $columns;
    }

    // « Voix (S, A, T ou B) » devient « voix », « Prénom » devient « prenom », « E-mail » devient « email »
    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/\(.*?\)/u', '', $header) ?? $header;

        return preg_replace('/[^a-z]+/', '', Str::lower(Str::ascii($header))) ?? '';
    }

    private function cellToString(mixed $value): string
    {
        $text = match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            // Un numéro de téléphone saisi comme nombre arrive en flottant : pas de « .0 » ni de notation scientifique
            is_float($value) && floor($value) === $value => (string) (int) $value,
            is_scalar($value) => (string) $value,
            default => '',
        };

        // Retire aussi les espaces insécables, fréquents dans les données copiées depuis le web ou un PDF
        $text = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $text) ?? $text;

        // Un tiret seul (« - ») est la façon de répondre « sans objet » dans un formulaire : cellule vide
        return preg_match('/^[\-\x{2013}\x{2014}.\/_]+$/u', $text) === 1 ? '' : $text;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, int>  $groups
     * @return array<string, mixed> name_problem : motif si « Nom et prénom(s) » n'a pas pu être séparé, sinon null
     */
    private function normalize(array $values, array $groups): array
    {
        // La voix est reconnue par son initiale ou son nom (« Ténor »), sans tenir compte de la casse ni des accents ;
        // sinon la valeur saisie est gardée pour le message d'erreur
        $part = VoicePart::fromLabel($values['voice']);

        if ($part !== null && isset($groups[$part->value])) {
            $values['voice'] = $part->value;
        }

        $nameProblem = null;

        if ($values['full_name'] !== '') {
            $name = ($this->splitFullName)($values['full_name']);

            if ($name === null) {
                $nameProblem = 'Nom et prénom(s) impossibles à séparer : écrivez le NOM en majuscules, puis le prénom (exemple : RAKOTO Fanja).';
            } else {
                $values = [...$values, ...$name,];
            }
        }

        return [
            ...$values,
            'phone'        => $values['phone'] === '' ? null : $values['phone'],
            ...($this->parseRecensement)($values),
            'name_problem' => $nameProblem,
        ];
    }

    /**
     * @param  list<string>  $voices
     * @return array<string, list<mixed>>
     */
    private function rules(array $voices): array
    {
        return [
            'last_name'  => ['bail', 'required', 'string', 'max:100',],
            'first_name' => ['bail', 'required', 'string', 'max:100',],
            'voice'      => ['bail', 'required', Rule::in($voices),],
            'phone'      => ['nullable', 'string', 'max:30',],
            'mail'       => ['bail', 'required', 'email', 'max:255',],
            // Colonnes limitées en base : mieux vaut refuser la ligne que tronquer en silence
            'birth_place'         => ['nullable', 'string', 'max:255',],
            'registration_number' => ['nullable', 'string', 'max:255',],
            'spouse_name'         => ['nullable', 'string', 'max:255',],
            'emergency_name'      => ['nullable', 'string', 'max:255',],
            'emergency_phone'     => ['nullable', 'string', 'max:50',],
            'emergency_relationship' => ['nullable', 'string', 'max:100',],
            'social_pseudo'          => ['nullable', 'string', 'max:255',],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'last_name.required'  => 'Nom manquant.',
            'last_name.max'       => 'Nom trop long (100 caractères maximum).',
            'first_name.required' => 'Prénom manquant.',
            'first_name.max'      => 'Prénom trop long (100 caractères maximum).',
            'voice.required'      => 'Voix manquante : indiquez S, A, T ou B.',
            'voice.in'            => 'Voix « :input » non reconnue : indiquez S, A, T ou B.',
            'phone.max'           => 'Téléphone trop long (30 caractères maximum).',
            'mail.required'       => 'Email manquant.',
            'mail.email'          => 'Email invalide : « :input ».',
            'mail.max'            => 'Email trop long (255 caractères maximum).',
            'birth_place.max'         => 'Lieu de naissance trop long (255 caractères maximum).',
            'registration_number.max' => 'Matricule et quartier trop longs (255 caractères maximum).',
            'spouse_name.max'         => 'Conjoint trop long (255 caractères maximum).',
            'emergency_name.max'      => "Nom du contact d'urgence trop long (255 caractères maximum).",
            'emergency_phone.max'     => "Numéro du contact d'urgence trop long (50 caractères maximum).",
            'emergency_relationship.max' => "Type de contact d'urgence trop long (100 caractères maximum).",
            'social_pseudo.max'          => 'Pseudo Facebook ou Messenger trop long (255 caractères maximum).',
        ];
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function issue(int $line, array $values, string $message): UserImportIssueDTO
    {
        // Sans nom séparé, la saisie brute de « Nom et prénom(s) » permet quand même de retrouver la personne
        $name = trim($values['last_name'].' '.$values['first_name']);
        $name = $name === '' ? $values['full_name'] : $name;

        return new UserImportIssueDTO($line, $name === '' ? null : $name, $message);
    }
}
