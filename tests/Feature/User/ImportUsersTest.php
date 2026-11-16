<?php

namespace Tests\Feature\User;

use App\Actions\User\AnalyzeUsersImportAction;
use App\Actions\User\RunUsersImportAction;
use App\Enums\RoleName;
use App\Enums\UserImportStatus;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Jobs\ImportUsersJob;
use App\Models\Firstname;
use App\Models\User;
use App\Models\UserImport;
use App\Models\UserSacrament;
use App\Repositories\UserRepository;
use Closure;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ImportUsersTest extends TestCase
{
    use RefreshDatabase;

    private const array HEADER = ['Nom', 'Prénom', 'Voix (S, A, T ou B)', 'Téléphone', 'Email',];

    // En-têtes de l'onglet « Réponses » exporté depuis le formulaire de recensement, dans leur ordre réel
    private const array FORM_HEADER = [
        'Horodateur',
        'Nom et prénom(s)',
        'Adresse e-mail',
        'Adresse',
        'Numéro de téléphone',
        'Date et Lieu de naissance',
        'Voix',
        'Pseudo sur Facebook ou Messenger',
        "N° Matricule et Quartier (dans l'Ecar Faravohitra)",
        'Date du Baptême',
        'Lieu du Baptême',
        'Date de la Première Communion',
        'Lieu de la Première Communion',
        'Date de la Confirmation',
        'Lieu de la Confirmation',
        'Marié(e) à...',
        'Date du Mariage',
        'Lieu du Mariage',
        "Date d'adhésion à la chorale",
        "Lieu de l'engagement",
        "Date d'engagement",
        "A contacter en cas d'urgence",
        "Nom de la (ou des) personne(s) à contacter en cas d'urgence",
        "Numéro(s) de téléphone à contacter en cas d'urgence",
        "Photo d'identité récente",
    ];

    private const string UNSPLITTABLE_NAME = 'Nom et prénom(s) impossibles à séparer : écrivez le NOM en majuscules, puis le prénom (exemple : RAKOTO Fanja).';

    // PNG de 1 pixel : la réponse de Google Drive par défaut
    private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    // Réponse de Google Drive à chaque téléchargement ; un test la remplace pour simuler un lien privé, un fichier cassé...
    private Closure $driveResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, VoiceSeeder::class,]);
        Notification::fake();
        Storage::fake('public');
        // Fichier envoyé, gardé sur le disque privé le temps de l'import
        Storage::fake('local');

        // Aucun test ne doit joindre le vrai Google Drive
        $this->driveResponse = fn (): PromiseInterface => Http::response(
            base64_decode(self::PNG_BASE64),
            200,
            ['Content-Type' => 'image/png',],
        );
        Http::fake(fn (Request $request) => ($this->driveResponse)($request));
    }

    // ── Aperçu : POST /api/users/import/preview ─────────────────────────

    public function test_a_secretary_can_preview_a_valid_file(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['RAKOTONDRABE', 'Fanja', 'S', '034 12 345 67', 'fanja@example.com',],
            ['RAMANANTSOA', 'Toky', 'T', '032 00 000 00', 'toky@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.ready_count', 2)
            ->assertJsonPath('data.skipped_count', 0)
            ->assertJsonPath('data.error_count', 0)
            ->assertJsonPath('data.ready.0', [
                'line'       => 2,
                'last_name'  => 'RAKOTONDRABE',
                'first_name' => 'Fanja',
                'voice'      => 'S',
            ])
            ->assertJsonPath('data.ready.1.voice', 'T')
            ->assertJsonPath('data.errors', [])
            ->assertJsonPath('data.skipped', []);
    }

    public function test_an_admin_can_preview_a_file(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->previewFile([self::HEADER, $this->validRow(),])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1);
    }

    public function test_the_preview_writes_nothing(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $usersBefore = User::count();

        $this->previewFile([self::HEADER, $this->validRow(),])->assertOk();

        $this->assertSame($usersBefore, User::count());
        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('firstnames', 0);
        Notification::assertNothingSent();
    }

    public function test_invalid_rows_are_reported_with_their_excel_line_and_a_clear_message(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $rows = [self::HEADER,];

        // Lignes 2 à 16 : toutes valides sauf la 7 (voix inconnue) et la 15 (email manquant)
        for ($line = 2; $line <= 16; $line++) {
            $rows[] = ['Choriste'.$line, 'Prenom'.$line, 'SATB'[$line % 4], '034 00 000 '.$line, 'choriste'.$line.'@example.com',];
        }

        $rows[6] = ['RAKOTO', 'Nivo', 'Mezzo', '', 'nivo@example.com',];
        $rows[14] = ['ANDRIANINA', 'Sitraka', 'T', '', '',];

        $this->previewFile($rows)
            ->assertOk()
            ->assertJsonPath('data.total', 15)
            ->assertJsonPath('data.ready_count', 13)
            ->assertJsonPath('data.error_count', 2)
            ->assertJsonPath('data.errors', [
                ['line' => 7, 'name' => 'RAKOTO Nivo', 'message' => 'Voix « Mezzo » non reconnue : indiquez S, A, T ou B.',],
                ['line' => 15, 'name' => 'ANDRIANINA Sitraka', 'message' => 'Email manquant.',],
            ]);
    }

    public function test_line_numbers_follow_excel_when_the_file_has_blank_rows(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            $this->validRow('un@example.com'),
            [null, null, null, null, null,],
            [null, null, null, null, null,],
            ['RAKOTO', 'Nivo', 'X', '', 'nivo@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.errors.0.line', 5);
    }

    public function test_a_row_with_several_problems_lists_them_all(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([self::HEADER, ['', 'Nivo', 'X', '', 'pas-un-email',],])
            ->assertOk()
            ->assertJsonPath(
                'data.errors.0.message',
                'Nom manquant. Voix « X » non reconnue : indiquez S, A, T ou B. Email invalide : « pas-un-email ».',
            )
            ->assertJsonPath('data.errors.0.name', 'Nivo');
    }

    public function test_a_row_without_any_name_has_a_null_name(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([self::HEADER, ['', '', 'S', '', 'sans-nom@example.com',],])
            ->assertOk()
            ->assertJsonPath('data.errors.0.name', null)
            ->assertJsonPath('data.errors.0.message', 'Nom manquant. Prénom manquant.');
    }

    public function test_each_required_field_is_checked(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['', 'Fanja', 'S', '', 'a@example.com',],
            ['Rakoto', '', 'S', '', 'b@example.com',],
            ['Rakoto', 'Fanja', '', '', 'c@example.com',],
            ['Rakoto', 'Fanja', 'S', '', '',],
            ['Rakoto', 'Fanja', 'S', '', 'pas-un-email',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 0)
            ->assertJsonPath('data.errors.0.message', 'Nom manquant.')
            ->assertJsonPath('data.errors.1.message', 'Prénom manquant.')
            ->assertJsonPath('data.errors.2.message', 'Voix manquante : indiquez S, A, T ou B.')
            ->assertJsonPath('data.errors.3.message', 'Email manquant.')
            ->assertJsonPath('data.errors.4.message', 'Email invalide : « pas-un-email ».');
    }

    public function test_too_long_values_are_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            [str_repeat('a', 101), 'Fanja', 'S', '', 'a@example.com',],
            ['Rakoto', str_repeat('a', 101), 'S', '', 'b@example.com',],
            ['Rakoto', 'Fanja', 'S', str_repeat('1', 31), 'c@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.errors.0.message', 'Nom trop long (100 caractères maximum).')
            ->assertJsonPath('data.errors.1.message', 'Prénom trop long (100 caractères maximum).')
            ->assertJsonPath('data.errors.2.message', 'Téléphone trop long (30 caractères maximum).');
    }

    public function test_the_voice_is_accepted_whatever_its_case_and_surrounding_spaces(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Fanja', ' s ', '', 'a@example.com',],
            ['Rakoto', 'Toky', 'b', '', 'b@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 2)
            ->assertJsonPath('data.ready.0.voice', 'S')
            ->assertJsonPath('data.ready.1.voice', 'B');
    }

    public function test_only_the_four_voice_groups_are_accepted(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Fanja', 'Mezzo', '', 'a@example.com',],
            ['Rakoto', 'Toky', 'S1', '', 'b@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 0)
            ->assertJsonPath('data.errors.0.message', 'Voix « Mezzo » non reconnue : indiquez S, A, T ou B.')
            ->assertJsonPath('data.errors.1.message', 'Voix « S1 » non reconnue : indiquez S, A, T ou B.');
    }

    public function test_a_duplicate_email_inside_the_file_is_an_error_on_the_later_row(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Fanja', 'S', '', 'fanja@example.com',],
            ['Rasolofo', 'Miora', 'A', '', 'FANJA@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.ready.0.line', 2)
            ->assertJsonPath('data.errors.0.line', 3)
            ->assertJsonPath('data.errors.0.name', 'Rasolofo Miora')
            ->assertJsonPath('data.errors.0.message', 'Email déjà présent à la ligne 2 du fichier.');
    }

    public function test_an_invalid_row_does_not_reserve_its_email(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Fanja', 'Mezzo', '', 'fanja@example.com',],
            ['Rakoto', 'Fanja', 'S', '', 'fanja@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.ready.0.line', 3)
            ->assertJsonPath('data.errors.0.line', 2);
    }

    public function test_an_existing_account_is_skipped_whatever_the_case_of_the_email(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        User::factory()->create(['mail' => 'fanja@example.com', 'last_name' => 'Rakoto',]);

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Fanja', 'S', '', 'Fanja@Example.com',],
            ['Rasolofo', 'Miora', 'A', '', 'miora@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.error_count', 0)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped.0', [
                'line'    => 2,
                'name'    => 'Rakoto Fanja',
                'message' => 'Un compte existe déjà avec cet email.',
            ]);
    }

    public function test_an_existing_account_stored_with_a_mixed_case_email_is_still_found(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        User::factory()->create(['mail' => 'Fanja@Example.com',]);

        $this->previewFile([self::HEADER, ['Rakoto', 'Fanja', 'S', '', 'fanja@example.com',],])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 0)
            ->assertJsonPath('data.skipped_count', 1);
    }

    #[DataProvider('sameNameTwice')]
    public function test_the_same_name_and_first_names_inside_the_file_is_an_error_on_the_later_row(
        string $lastName,
        string $firstName,
    ): void {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Hélène Marie', 'S', '', 'helene@example.com',],
            [$lastName, $firstName, 'A', '', 'autre@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.ready.0.line', 2)
            ->assertJsonPath('data.errors.0.line', 3)
            ->assertJsonPath('data.errors.0.message', 'Nom et prénom(s) déjà présents à la ligne 2 du fichier.');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sameNameTwice(): array
    {
        return [
            'same words'                => ['Rakoto', 'Hélène Marie',],
            'case and accents'          => ['RAKOTO', 'helene marie',],
            'hyphen instead of a space' => ['Rakoto', 'Hélène-Marie',],
            'extra spaces'              => ['  Rakoto ', 'Hélène   Marie',],
        ];
    }

    public function test_the_same_first_name_with_another_last_name_is_not_a_duplicate(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Fanja', 'S', '', 'un@example.com',],
            ['Rabe', 'Fanja', 'A', '', 'deux@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 2)
            ->assertJsonPath('data.error_count', 0);
    }

    public function test_the_email_message_comes_first_when_the_email_and_the_name_are_both_duplicated(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Fanja', 'S', '', 'fanja@example.com',],
            ['Rakoto', 'Fanja', 'S', '', 'fanja@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.errors.0.message', 'Email déjà présent à la ligne 2 du fichier.');
    }

    public function test_an_existing_member_with_the_same_name_is_skipped_even_with_another_email(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->existingMember('ancien@example.com', 'Rakoto', ['Hélène Marie',]);

        $this->previewFile([
            self::HEADER,
            ['RAKOTO', 'helene marie', 'S', '', 'nouveau@example.com',],
            ['Rasolofo', 'Miora', 'A', '', 'miora@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.ready.0.line', 3)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped.0', [
                'line'    => 2,
                'name'    => 'RAKOTO helene marie',
                'message' => 'Un membre existe déjà avec ce nom et ces prénoms.',
            ]);
    }

    public function test_an_existing_member_is_found_by_its_default_first_name_or_by_all_its_first_names(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->existingMember('ancien@example.com', 'Rakoto', ['Fanja', 'Marie',]);

        // Prénom par défaut seul, puis tous les prénoms : doublons ; « Marie » seul : un autre membre
        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Fanja', 'S', '', 'un@example.com',],
            ['Rakoto', 'Fanja Marie', 'S', '', 'deux@example.com',],
            ['Rakoto', 'Marie', 'S', '', 'trois@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.skipped_count', 2)
            ->assertJsonPath('data.skipped.0.line', 2)
            ->assertJsonPath('data.skipped.1.line', 3)
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.ready.0.line', 4);
    }

    public function test_an_existing_member_with_another_last_name_is_not_a_duplicate(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->existingMember('ancien@example.com', 'Rabe', ['Fanja',]);

        $this->previewFile([self::HEADER, ['Rakoto', 'Fanja', 'S', '', 'nouveau@example.com',],])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.skipped_count', 0);
    }

    public function test_importing_a_member_whose_name_already_exists_creates_nothing_and_changes_nothing(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $existing = $this->existingMember('ancien@example.com', 'Rakoto', ['Fanja',]);
        $usersBefore = User::count();

        $this->importFile([self::HEADER, ['Rakoto', 'Fanja', 'S', '034 00 000 00', 'nouveau@example.com',],])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped.0.message', 'Un membre existe déjà avec ce nom et ces prénoms.');

        $this->assertSame($usersBefore, User::count());
        $this->assertDatabaseMissing('users', ['mail' => 'nouveau@example.com',]);
        $this->assertNull($existing->refresh()->profile);
    }

    public function test_the_columns_can_be_in_any_order_with_any_case_accents_or_synonyms(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            ['E-mail', 'VOIX', 'prenoms', 'nom', 'TÉLÉPHONE', 'Commentaire',],
            ['fanja@example.com', 'S', 'Fanja Marie', 'Rakoto', '034 12 345 67', 'ignoré',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.ready.0.first_name', 'Fanja Marie')
            ->assertJsonPath('data.ready.0.last_name', 'Rakoto');
    }

    public function test_the_phone_column_is_optional(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            ['Nom', 'Prénom', 'Voix', 'Email',],
            ['Rakoto', 'Fanja', 'S', 'fanja@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1);
    }

    public function test_a_file_exported_by_the_members_export_can_be_read_back(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            ['Nom', 'Prénoms', 'Voix', 'Voix précise', 'Téléphone', 'Email',],
            ['Rakoto', 'Fanja Marie', 'S', 'S1', '034 12 345 67', 'fanja@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1);
    }

    public function test_the_voice_is_accepted_by_name_whatever_the_case_and_accents(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::HEADER,
            ['Rakoto', 'Un', 'Soprano', '', 'a@example.com',],
            ['Rakoto', 'Deux', 'ALTO', '', 'b@example.com',],
            ['Rakoto', 'Trois', ' Ténor ', '', 'c@example.com',],
            ['Rakoto', 'Quatre', 'tenor', '', 'd@example.com',],
            ['Rakoto', 'Cinq', 'Basse', '', 'e@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 5)
            ->assertJsonPath('data.ready.0.voice', 'S')
            ->assertJsonPath('data.ready.1.voice', 'A')
            ->assertJsonPath('data.ready.2.voice', 'T')
            ->assertJsonPath('data.ready.3.voice', 'T')
            ->assertJsonPath('data.ready.4.voice', 'B');
    }

    // ── Export du formulaire de recensement (colonne unique « Nom et prénom(s) ») ──

    public function test_the_registration_form_export_is_read_as_is(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::FORM_HEADER,
            $this->formRow('RAKOTO Exemple', 'rakoto.exemple@mail.com', '034 00 000 01', 'Ténor'),
            $this->formRow('RASOA Exemple', 'rasoa.ex@mail.com', '+261 32 00 000 04', 'Soprano'),
            // La photo est vide : Google Sheets n'écrit pas la dernière cellule, la ligne a une colonne de moins
            array_slice($this->formRow('RABE Exemple', 'rabe.exemple@mail.com', '+33 6 00 00 00 06', 'Basse'), 0, 24),
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.ready_count', 3)
            ->assertJsonPath('data.error_count', 0)
            ->assertJsonPath('data.ready.0', ['line' => 2, 'last_name' => 'RAKOTO', 'first_name' => 'Exemple', 'voice' => 'T',])
            ->assertJsonPath('data.ready.1.voice', 'S')
            ->assertJsonPath('data.ready.2.voice', 'B');
    }

    public function test_the_registration_form_export_is_imported_with_split_names_and_the_members_own_phone(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([
            self::FORM_HEADER,
            $this->formRow('RAKOTO ANDRIA Fanja Marie', 'fanja@example.com', '034 00 000 01', 'Ténor'),
            $this->formRow('Toky RAMANANTSOA', 'toky@example.com', '+261 32 00 000 04', 'Alto'),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.error_count', 0);

        // Le numéro à contacter en cas d'urgence (colonne « Numéro(s) de téléphone à contacter... ») n'est pas repris
        $fanja = User::where('mail', 'fanja@example.com')->firstOrFail();
        $this->assertSame('RAKOTO ANDRIA', $fanja->last_name);
        $this->assertSame('Fanja Marie', $fanja->firstnames()->sole()->first_name);
        $this->assertSame('T', $fanja->profile->voiceGroup->abbreviation);
        $this->assertSame('034 00 000 01', $fanja->profile->phone);

        $toky = User::where('mail', 'toky@example.com')->firstOrFail();
        $this->assertSame('RAMANANTSOA', $toky->last_name);
        $this->assertSame('Toky', $toky->firstnames()->sole()->first_name);
        $this->assertSame('A', $toky->profile->voiceGroup->abbreviation);
        $this->assertSame('+261 32 00 000 04', $toky->profile->phone);
    }

    public function test_a_full_name_that_cannot_be_split_is_reported_with_its_raw_value(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            ['Nom et prénom(s)', 'Voix', 'Email',],
            ['Fanja Rakoto', 'S', 'a@example.com',],
            ['RAKOTO', 'S', 'b@example.com',],
            ['FANJA RAKOTO', 'S', 'c@example.com',],
            ['RAKOTO Fanja', 'S', 'd@example.com',],
            ['Fanja Rakoto', 'X', 'e@example.com',],
            ['', 'S', 'f@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.error_count', 5)
            ->assertJsonPath('data.errors.0', ['line' => 2, 'name' => 'Fanja Rakoto', 'message' => self::UNSPLITTABLE_NAME,])
            ->assertJsonPath('data.errors.1', ['line' => 3, 'name' => 'RAKOTO', 'message' => self::UNSPLITTABLE_NAME,])
            ->assertJsonPath('data.errors.2', ['line' => 4, 'name' => 'FANJA RAKOTO', 'message' => self::UNSPLITTABLE_NAME,])
            ->assertJsonPath(
                'data.errors.3.message',
                self::UNSPLITTABLE_NAME.' Voix « X » non reconnue : indiquez S, A, T ou B.',
            )
            ->assertJsonPath('data.errors.4', ['line' => 7, 'name' => null, 'message' => 'Nom manquant. Prénom manquant.',]);
    }

    public function test_the_last_name_can_also_come_last_in_the_full_name(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            ['Nom et prénom(s)', 'Voix', 'Email',],
            ['Fanja Marie RAKOTO', 'S', 'a@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready.0.last_name', 'RAKOTO')
            ->assertJsonPath('data.ready.0.first_name', 'Fanja Marie');
    }

    public function test_separate_name_columns_win_over_the_full_name_column(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            ['Nom et prénom(s)', 'Nom', 'Prénom', 'Voix', 'Email',],
            ['Fanja Rakoto', 'Rakoto', 'Fanja', 'S', 'a@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.ready.0.last_name', 'Rakoto')
            ->assertJsonPath('data.ready.0.first_name', 'Fanja');
    }

    public function test_missing_columns_are_named_when_the_file_has_a_full_name_column(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([['Nom et prénom(s)', 'Téléphone',], ['RAKOTO Fanja', '034',],])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Colonnes manquantes : Voix, Email. Utilisez le modèle de fichier.');
    }

    public function test_unreadable_recensement_information_is_a_warning_and_does_not_block_the_row(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::FORM_HEADER,
            $this->formRow('RAKOTO Exemple', 'rakoto@example.com', '034 00 000 01', 'Ténor', $this->unreadableAnswers()),
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.error_count', 0)
            ->assertJsonPath('data.warning_count', 6)
            ->assertJsonPath('data.warnings.0', [
                'line'    => 2,
                'name'    => 'RAKOTO Exemple',
                'message' => 'Date de naissance « 1995 » incomplète (jour, mois et année attendus) : non enregistrée.',
            ])
            ->assertJsonPath('data.warnings.1.message', "Date d'adhésion « Septembre 2019 » non reconnue (jour, mois et année attendus) : non enregistrée.")
            ->assertJsonPath('data.warnings.2.message', "Date d'engagement « Je ne sais plus » non reconnue (jour, mois et année attendus) : non enregistrée.")
            ->assertJsonPath('data.warnings.3.message', 'Date du mariage « 31/02/2020 » non reconnue (jour, mois et année attendus) : non enregistrée.')
            ->assertJsonPath('data.warnings.4.message', 'Photo « pas de photo » : seuls les liens Google Drive sont repris, non enregistrée.')
            ->assertJsonPath('data.warnings.5.message', "Numéro du contact d'urgence sans nom : non enregistré.");
    }

    public function test_the_columns_that_are_not_imported_are_listed(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([self::FORM_HEADER, $this->formRow('RAKOTO Exemple', 'rakoto@example.com', '034 00 000 01', 'Ténor'),])
            ->assertOk()
            ->assertJsonPath('data.ignored_columns', ['Horodateur',]);
    }

    public function test_a_file_with_the_short_columns_has_no_ignored_column_and_no_warning(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([self::HEADER, $this->validRow(),])
            ->assertOk()
            ->assertJsonPath('data.ignored_columns', [])
            ->assertJsonPath('data.warning_count', 0)
            ->assertJsonPath('data.warnings', []);
    }

    public function test_recensement_values_too_long_for_their_column_are_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([
            self::FORM_HEADER,
            $this->formRow('RAKOTO Exemple', 'rakoto@example.com', '034 00 000 01', 'Ténor', [
                'Date et Lieu de naissance'                                    => '12/03/1998 à '.str_repeat('a', 256),
                "N° Matricule et Quartier (dans l'Ecar Faravohitra)"           => str_repeat('1', 256),
                'Marié(e) à...'                                                => str_repeat('a', 256),
                "Nom de la (ou des) personne(s) à contacter en cas d'urgence"  => str_repeat('a', 256),
                "Numéro(s) de téléphone à contacter en cas d'urgence"          => str_repeat('1', 51),
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 0)
            ->assertJsonPath(
                'data.errors.0.message',
                "Lieu de naissance trop long (255 caractères maximum). Matricule et quartier trop longs (255 caractères maximum). Conjoint trop long (255 caractères maximum). Nom du contact d'urgence trop long (255 caractères maximum). Numéro du contact d'urgence trop long (50 caractères maximum).",
            );
    }

    public function test_missing_required_columns_are_named(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([['Nom', 'Téléphone',], ['Rakoto', '034',],])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file',])
            ->assertJsonPath('errors.file.0', 'Colonnes manquantes : Prénom, Voix, Email. Utilisez le modèle de fichier.');
    }

    public function test_a_file_without_data_rows_is_refused(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([self::HEADER,])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Le fichier ne contient aucune ligne à importer.');

        $this->previewFile([self::HEADER, [null, null, null, null, null,],])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Le fichier ne contient aucune ligne à importer.');
    }

    public function test_an_empty_workbook_is_refused(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Le fichier est vide.');
    }

    public function test_a_file_over_the_row_limit_is_refused(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $rows = [self::HEADER,];

        for ($i = 1; $i <= AnalyzeUsersImportAction::MAX_ROWS + 1; $i++) {
            $rows[] = $this->validRow('choriste'.$i.'@example.com');
        }

        $this->previewFile($rows)
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Le fichier dépasse 500 lignes : découpez-le en plusieurs fichiers.');
    }

    public function test_a_file_at_the_row_limit_is_accepted(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $rows = [self::HEADER,];

        for ($i = 1; $i <= AnalyzeUsersImportAction::MAX_ROWS; $i++) {
            $rows[] = $this->validRow('choriste'.$i.'@example.com', 'Choriste'.$i);
        }

        $this->previewFile($rows)
            ->assertOk()
            ->assertJsonPath('data.ready_count', AnalyzeUsersImportAction::MAX_ROWS);
    }

    public function test_a_corrupted_file_named_xlsx_is_refused_with_a_clear_message(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/users/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('membres.xlsx', 'ceci n\'est pas un classeur'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Le fichier est illisible : utilisez un fichier Excel (.xlsx), de préférence le modèle.');
    }

    public function test_the_file_is_required(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/users/import/preview')
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Le fichier est obligatoire.');
    }

    public function test_only_xlsx_files_are_accepted(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        foreach (['membres.csv', 'membres.xls', 'membres.pdf',] as $name) {
            $this->postJson('/api/users/import/preview', [
                'file' => UploadedFile::fake()->createWithContent($name, 'Nom;Prénom'),
            ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.file.0', 'Le fichier doit être au format Excel (.xlsx).');
        }
    }

    public function test_a_file_over_5_mb_is_refused(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/users/import/preview', [
            'file' => UploadedFile::fake()->create('membres.xlsx', 6000),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Le fichier ne doit pas dépasser 5 Mo.');
    }

    // ── Import : POST /api/users/import ─────────────────────────────────

    public function test_a_secretary_imports_the_valid_rows(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([
            self::HEADER,
            ['RAKOTONDRABE', 'Fanja', 'S', '034 12 345 67', 'fanja@example.com',],
            ['RAMANANTSOA', 'Toky', 'T', '032 00 000 00', 'toky@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.skipped_count', 0)
            ->assertJsonPath('data.error_count', 0)
            ->assertJsonPath('data.errors', []);

        $fanja = User::where('mail', 'fanja@example.com')->firstOrFail();
        $this->assertSame('RAKOTONDRABE', $fanja->last_name);
        $this->assertSame('Fanja', $fanja->firstnames()->sole()->first_name);
        $this->assertTrue($fanja->firstnames()->sole()->default);
        $this->assertSame('S', $fanja->profile->voiceGroup->abbreviation);
        $this->assertNull($fanja->profile->voice_id);
        $this->assertSame('034 12 345 67', $fanja->profile->phone);

        $toky = User::where('mail', 'toky@example.com')->firstOrFail();
        $this->assertSame('T', $toky->profile->voiceGroup->abbreviation);
    }

    public function test_the_whole_registration_form_row_is_saved_in_the_existing_tables(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([
            self::FORM_HEADER,
            $this->formRow('RAKOTO ANDRIA Fanja Marie', 'fanja@example.com', '034 00 000 01', 'Ténor', [
                "Date d'adhésion à la chorale" => '03/09/2019',
                'Marié(e) à...'                => 'RASOA Exemple, membre de la CFF',
                'Date du Mariage'              => '20/07/2021',
                'Lieu du Mariage'              => 'Ecar Faravohitra',
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.warning_count', 0)
            ->assertJsonPath('data.warnings', []);

        $user = User::where('mail', 'fanja@example.com')->firstOrFail();

        $profile = $user->profile;
        $this->assertSame('Lot IVB 12 Faravohitra, Antananarivo', $profile->address);
        $this->assertSame('1998-03-12', $profile->birth_date->toDateString());
        $this->assertSame('Antananarivo', $profile->birth_place);
        $this->assertSame('1234 Faravohitra', $profile->region_registration_number);

        // La photo est téléchargée dans le dossier de l'application ; le lien Drive n'est pas gardé
        $photos = Storage::disk('public')->files('members/photos');
        $this->assertCount(1, $photos);
        $this->assertStringEndsWith('.png', $photos[0]);
        $this->assertStringEndsWith('/'.$photos[0], $profile->picture_url);
        $this->assertSame(base64_decode(self::PNG_BASE64), Storage::disk('public')->get($photos[0]));
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://drive.usercontent.google.com/download?')
            && str_contains($request->url(), 'id=exemple1'));

        $engagement = $user->engagement;
        $this->assertSame('2019-09-03', $engagement->adhesion_date->toDateString());
        $this->assertSame('2020-12-08', $engagement->engagement_date->toDateString());
        $this->assertSame('Ecar Faravohitra', $engagement->engagement_place);

        $this->assertSame('Pseudo Exemple', $profile->social_pseudo);

        // Une ligne par sacrement
        $sacraments = $user->sacraments->keyBy(fn (UserSacrament $sacrament): string => $sacrament->type->value);
        $this->assertSame(['baptism', 'first_communion', 'confirmation', 'marriage',], $sacraments->keys()->all());

        $this->assertSame('1998-06-05', $sacraments['baptism']->date->toDateString());
        $this->assertSame('Ecar Faravohitra', $sacraments['baptism']->place);
        $this->assertNull($sacraments['baptism']->spouse_name);
        $this->assertSame('2008-05-14', $sacraments['first_communion']->date->toDateString());
        $this->assertSame('Ecar Faravohitra', $sacraments['first_communion']->place);
        $this->assertSame('2012-08-15', $sacraments['confirmation']->date->toDateString());
        $this->assertSame('Cathédrale Andohalo', $sacraments['confirmation']->place);

        $marriage = $sacraments['marriage'];
        $this->assertSame('RASOA Exemple, membre de la CFF', $marriage->spouse_name);
        $this->assertTrue($marriage->spouse_is_member);
        $this->assertSame('2021-07-20', $marriage->date->toDateString());
        $this->assertSame('Ecar Faravohitra', $marriage->place);

        $contact = $user->emergencyContacts()->sole();
        $this->assertSame('RAKOTO Papa, RAKOTO Maman', $contact->contact_name);
        $this->assertSame('034 00 000 02 / 033 00 000 03', $contact->contact_phone);
        $this->assertSame('Parents', $contact->relationship);
        $this->assertNotEmpty($contact->uuid);
    }

    public function test_a_row_with_unreadable_information_is_created_without_it_and_reported(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([
            self::FORM_HEADER,
            $this->formRow('RAKOTO Exemple', 'rakoto@example.com', '034 00 000 01', 'Ténor', $this->unreadableAnswers()),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.warning_count', 6)
            ->assertJsonPath('data.warnings.0.line', 2)
            ->assertJsonPath('data.warnings.0.name', 'RAKOTO Exemple');

        $user = User::where('mail', 'rakoto@example.com')->firstOrFail();

        $this->assertNull($user->profile->birth_date);
        $this->assertSame('Fianarantsoa', $user->profile->birth_place);
        $this->assertNull($user->profile->picture_url);
        $this->assertNull($user->engagement->adhesion_date);
        $this->assertNull($user->engagement->engagement_date);
        $this->assertSame('Ecar Faravohitra', $user->engagement->engagement_place);
        // Le mariage est illisible : il n'a pas de ligne ; le baptême, la communion et la confirmation sont lus
        $this->assertDatabaseMissing('user_sacraments', ['type' => 'marriage',]);
        $this->assertSame(3, $user->sacraments()->count());
        $this->assertDatabaseCount('user_emergency_contacts', 0);
    }

    public function test_empty_or_dash_answers_create_no_extra_rows(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([
            self::FORM_HEADER,
            $this->formRow('RAKOTO Exemple', 'rakoto@example.com', '034 00 000 01', 'Basse', [
                'Adresse'                                                      => '',
                'Date et Lieu de naissance'                                    => '',
                "N° Matricule et Quartier (dans l'Ecar Faravohitra)"           => '-',
                "Date d'adhésion à la chorale"                                 => '',
                "Lieu de l'engagement"                                         => '',
                "Date d'engagement"                                            => '',
                "Nom de la (ou des) personne(s) à contacter en cas d'urgence"  => '',
                "Numéro(s) de téléphone à contacter en cas d'urgence"          => '',
                "Photo d'identité récente"                                     => '',
                'Pseudo sur Facebook ou Messenger'                             => '-',
                'Date du Baptême'                                              => '',
                'Lieu du Baptême'                                              => '',
                'Date de la Première Communion'                                => '',
                'Lieu de la Première Communion'                                => '',
                'Date de la Confirmation'                                      => '',
                'Lieu de la Confirmation'                                      => '',
                "A contacter en cas d'urgence"                                 => '',
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.warning_count', 0);

        $profile = User::where('mail', 'rakoto@example.com')->firstOrFail()->profile;
        $this->assertNull($profile->social_pseudo);
        $this->assertNull($profile->address);
        $this->assertNull($profile->birth_date);
        $this->assertNull($profile->birth_place);
        $this->assertNull($profile->region_registration_number);
        $this->assertNull($profile->picture_url);
        $this->assertDatabaseCount('user_engagements', 0);
        $this->assertDatabaseCount('user_sacraments', 0);
        $this->assertDatabaseCount('user_emergency_contacts', 0);
    }

    public function test_the_preview_downloads_no_photo(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->previewFile([self::FORM_HEADER, $this->formRow('RAKOTO Exemple', 'rakoto@example.com', '034 00 000 01', 'Ténor'),])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1);

        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_private_drive_link_creates_the_member_without_the_photo_and_says_so(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->driveResponse = fn (): PromiseInterface => Http::response(
            '<html>Connexion Google</html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8',],
        );

        $this->importFile([self::FORM_HEADER, $this->photoRow('RAKOTO Exemple', 'rakoto@example.com'),])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.warning_count', 1)
            ->assertJsonPath('data.warnings.0.line', 2)
            ->assertJsonPath('data.warnings.0.name', 'RAKOTO Exemple')
            ->assertJsonPath(
                'data.warnings.0.message',
                'Photo non téléchargée : lien privé : partagez la photo avec « Toute personne disposant du lien ».',
            );

        $this->assertNull(User::where('mail', 'rakoto@example.com')->firstOrFail()->profile->picture_url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_drive_file_that_is_not_an_image_is_not_stored(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->driveResponse = fn (): PromiseInterface => Http::response('%PDF-1.4 ...', 200, ['Content-Type' => 'application/pdf',]);

        $this->importFile([self::FORM_HEADER, $this->photoRow('RAKOTO Exemple', 'rakoto@example.com'),])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath(
                'data.warnings.0.message',
                'Photo non téléchargée : format non pris en charge (JPEG, PNG, WebP ou GIF attendus).',
            );

        $this->assertNull(User::where('mail', 'rakoto@example.com')->firstOrFail()->profile->picture_url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_drive_file_that_cannot_be_found_is_reported(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->driveResponse = fn (): PromiseInterface => Http::response('', 404);

        $this->importFile([self::FORM_HEADER, $this->photoRow('RAKOTO Exemple', 'rakoto@example.com'),])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath(
                'data.warnings.0.message',
                'Photo non téléchargée : lien privé, introuvable ou fichier de plus de 5 Mo.',
            );

        $this->assertNull(User::where('mail', 'rakoto@example.com')->firstOrFail()->profile->picture_url);
    }

    public function test_a_download_that_fails_to_connect_does_not_stop_the_import(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->driveResponse = fn () => Http::failedConnection('Délai dépassé');

        $this->importFile([self::FORM_HEADER, $this->photoRow('RAKOTO Exemple', 'rakoto@example.com'),])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath(
                'data.warnings.0.message',
                'Photo non téléchargée : lien privé, introuvable ou fichier de plus de 5 Mo.',
            );

        $this->assertNotNull(User::where('mail', 'rakoto@example.com')->first());
    }

    public function test_a_link_that_is_not_google_drive_is_never_requested(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([
            self::FORM_HEADER,
            $this->photoRow('RAKOTO Exemple', 'rakoto@example.com', 'http://169.254.169.254/latest/meta-data'),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath(
                'data.warnings.0.message',
                'Photo « http://169.254.169.254/latest/meta-data » : seuls les liens Google Drive sont repris, non enregistrée.',
            );

        Http::assertNothingSent();
        $this->assertNull(User::where('mail', 'rakoto@example.com')->firstOrFail()->profile->picture_url);
    }

    public function test_each_member_gets_their_own_photo(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([
            self::FORM_HEADER,
            $this->photoRow('RAKOTO Un', 'un@example.com', 'https://drive.google.com/open?id=photoUn'),
            $this->photoRow('RABE Deux', 'deux@example.com', 'https://drive.google.com/file/d/photoDeux/view'),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.warning_count', 0);

        $un = User::where('mail', 'un@example.com')->firstOrFail()->profile->picture_url;
        $deux = User::where('mail', 'deux@example.com')->firstOrFail()->profile->picture_url;

        $this->assertNotNull($un);
        $this->assertNotNull($deux);
        $this->assertNotSame($un, $deux);
        $this->assertCount(2, Storage::disk('public')->files('members/photos'));
        Http::assertSentCount(2);
    }

    public function test_an_admin_can_import(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->importFile([self::HEADER, $this->validRow(),])
            ->assertOk()
            ->assertJsonPath('data.created', 1);
    }

    public function test_imported_accounts_are_active_verified_members(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([self::HEADER, $this->validRow('fanja@example.com'),])->assertOk();

        $member = User::where('mail', 'fanja@example.com')->firstOrFail();
        $this->assertSame(UserStatus::Active, $member->status);
        $this->assertTrue($member->hasVerifiedEmail());
        $this->assertSame([RoleName::Member->value,], $member->getRoleNames()->all());
    }

    public function test_an_imported_member_passes_the_active_account_check(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->importFile([self::HEADER, $this->validRow('fanja@example.com'),])->assertOk();
        $member = User::where('mail', 'fanja@example.com')->firstOrFail();

        // 403 de la Policy (rôle), sans le `code` du middleware `account.active` : le compte est bien actif
        Sanctum::actingAs($member);
        $this->postJson("/api/members/{$member->id}/validate")
            ->assertForbidden()
            ->assertJsonMissingPath('code');
    }

    public function test_imported_accounts_get_an_unknown_random_password(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([self::HEADER, $this->validRow('a@example.com'),])->assertOk();

        $member = User::where('mail', 'a@example.com')->firstOrFail();

        $this->assertTrue(Hash::isHashed($member->password));
        $this->assertFalse(Hash::check('password', $member->password));
        $this->assertFalse(Hash::check('Password123!', $member->password));
        $this->assertFalse(Hash::check('', $member->password));

        $this->postJson('/api/auth/login', ['mail' => 'a@example.com', 'password' => 'password',])->assertUnauthorized();
    }

    public function test_no_email_is_sent_to_the_imported_members(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([self::HEADER, $this->validRow(),])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_the_import_is_partial_invalid_rows_are_reported_and_the_others_created(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $usersBefore = User::count();

        $this->importFile([
            self::HEADER,
            ['RAKOTONDRABE', 'Fanja', 'S', '', 'fanja@example.com',],
            ['RAKOTO', 'Nivo', 'Mezzo', '', 'nivo@example.com',],
            ['ANDRIANINA', 'Sitraka', 'T', '', '',],
            ['RAMANANTSOA', 'Toky', 'T', '', 'toky@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.error_count', 2)
            ->assertJsonPath('data.errors.0.line', 3)
            ->assertJsonPath('data.errors.0.message', 'Voix « Mezzo » non reconnue : indiquez S, A, T ou B.')
            ->assertJsonPath('data.errors.1.line', 4)
            ->assertJsonPath('data.errors.1.message', 'Email manquant.');

        $this->assertSame($usersBefore + 2, User::count());
        $this->assertDatabaseMissing('users', ['mail' => 'nivo@example.com',]);
    }

    public function test_an_existing_account_is_left_untouched_and_reported_as_skipped(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $existing = User::factory()->create(['mail' => 'fanja@example.com', 'last_name' => 'Original',]);

        $this->importFile([
            self::HEADER,
            ['Remplacé', 'Fanja', 'S', '034 00 000 00', 'fanja@example.com',],
            ['Rasolofo', 'Miora', 'A', '', 'miora@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped.0.line', 2)
            ->assertJsonPath('data.skipped.0.message', 'Un compte existe déjà avec cet email.');

        $this->assertSame('Original', $existing->refresh()->last_name);
        $this->assertNull($existing->profile);
        $this->assertDatabaseCount('firstnames', 1);
    }

    public function test_a_duplicate_inside_the_file_creates_only_the_first_row(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([
            self::HEADER,
            ['Rakoto', 'Fanja', 'S', '', 'fanja@example.com',],
            ['Rasolofo', 'Miora', 'A', '', 'fanja@example.com',],
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.errors.0.line', 3);

        $this->assertSame('Rakoto', User::where('mail', 'fanja@example.com')->sole()->last_name);
    }

    public function test_importing_the_same_file_twice_creates_nothing_the_second_time(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $rows = [self::HEADER, $this->validRow('a@example.com', 'Alice'), $this->validRow('b@example.com', 'Bob'),];

        $this->importFile($rows)->assertOk()->assertJsonPath('data.created', 2);
        $usersAfterFirst = User::count();

        $this->importFile($rows)
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped_count', 2);

        $this->assertSame($usersAfterFirst, User::count());
    }

    public function test_the_email_is_stored_in_lowercase_and_values_are_trimmed(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([self::HEADER, ["  Rakoto\u{00A0}", ' Fanja ', 's', ' 034 12 345 67 ', '  Fanja@Example.COM ',],])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $member = User::where('mail', 'fanja@example.com')->firstOrFail();
        $this->assertSame('Rakoto', $member->last_name);
        $this->assertSame('Fanja', $member->firstnames()->sole()->first_name);
        $this->assertSame('034 12 345 67', $member->profile->phone);
    }

    public function test_a_phone_typed_as_a_number_is_stored_without_decimals(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([self::HEADER, ['Rakoto', 'Fanja', 'S', 341234567, 'fanja@example.com',],])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertSame('341234567', User::where('mail', 'fanja@example.com')->firstOrFail()->profile->phone);
    }

    public function test_a_missing_phone_is_stored_as_null(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->importFile([self::HEADER, ['Rakoto', 'Fanja', 'S', '', 'fanja@example.com',],])->assertOk();

        $this->assertNull(User::where('mail', 'fanja@example.com')->firstOrFail()->profile->phone);
    }

    public function test_a_file_without_any_valid_row_creates_nothing(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $usersBefore = User::count();

        $this->importFile([self::HEADER, ['Rakoto', 'Fanja', 'Mezzo', '', 'fanja@example.com',],])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.error_count', 1);

        $this->assertSame($usersBefore, User::count());
    }

    public function test_an_account_created_by_someone_else_during_the_import_is_reported_as_skipped(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->partialMock(UserRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createActive')
                ->once()
                ->andThrow(new UniqueConstraintViolationException('sqlite', 'insert into "users"', [], new Exception('UNIQUE constraint failed')));
        });

        $this->importFile([self::HEADER, $this->validRow('fanja@example.com'),])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped.0.line', 2)
            ->assertJsonPath('data.skipped.0.message', 'Un compte existe déjà avec cet email.');
    }

    public function test_the_import_refuses_an_invalid_file_before_writing_anything(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $usersBefore = User::count();

        $this->importFile([['Nom', 'Téléphone',], ['Rakoto', '034',],])->assertUnprocessable();

        $this->assertSame($usersBefore, User::count());
    }

    // ── Modèle : GET /api/users/import/template ─────────────────────────

    public function test_a_secretary_can_download_the_template(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $response = $this->get('/api/users/import/template', ['Accept' => 'application/json',]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('modele-import-membres.xlsx');
        // Les 25 en-têtes du formulaire de recensement, dans l'ordre de l'onglet « Réponses »
        $this->assertSame(
            [self::FORM_HEADER,],
            $this->readWorkbook($response->streamedContent()),
        );
    }

    public function test_an_admin_can_download_the_template(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->get('/api/users/import/template', ['Accept' => 'application/json',])->assertOk();
    }

    public function test_the_template_is_accepted_by_the_import_once_filled(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $headers = $this->readWorkbook($this->get('/api/users/import/template')->streamedContent())[0];
        $this->assertSame(self::FORM_HEADER, $headers);

        // Seule « Horodateur » n'est pas reprise : le modèle rempli est lu comme un export du formulaire
        $this->previewFile([$headers, $this->formRow('RAKOTO Exemple', 'rakoto@example.com', '034 00 000 01', 'Ténor'),])
            ->assertOk()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.ignored_columns', ['Horodateur',]);
    }

    // ── Autorisations ───────────────────────────────────────────────────

    public function test_a_member_cannot_use_the_import(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $usersBefore = User::count();

        $this->previewFile([self::HEADER, $this->validRow(),])->assertForbidden();
        $this->importFile([self::HEADER, $this->validRow(),])->assertForbidden();
        $this->getJson('/api/users/import/template')->assertForbidden();

        $this->assertSame($usersBefore, User::count());
    }

    public function test_the_policy_runs_before_the_file_validation(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Member));

        $this->postJson('/api/users/import')->assertForbidden();
        $this->postJson('/api/users/import/preview')->assertForbidden();
    }

    public function test_a_guest_cannot_use_the_import(): void
    {
        $this->previewFile([self::HEADER, $this->validRow(),])->assertUnauthorized();
        $this->importFile([self::HEADER, $this->validRow(),])->assertUnauthorized();
        $this->getJson('/api/users/import/template')->assertUnauthorized();
    }

    public function test_a_pending_secretary_cannot_use_the_import(): void
    {
        $secretary = User::factory()->pending()->create();
        $secretary->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($secretary);
        $usersBefore = User::count();

        $this->importFile([self::HEADER, $this->validRow(),])
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);

        $this->assertSame($usersBefore, User::count());
    }

    // ── Import en tâche de fond : POST /api/users/import puis GET /api/users/import/{id} ──

    public function test_the_import_is_queued_and_answers_202_with_its_progress(): void
    {
        Queue::fake();
        $secretary = $this->userWithRole(RoleName::Secretary);
        Sanctum::actingAs($secretary);
        $usersBefore = User::count();

        // Deux comptes et une photo : 3 étapes
        $response = $this->postJson('/api/users/import', ['file' => $this->xlsx([
            self::FORM_HEADER,
            $this->photoRow('RAKOTO Exemple', 'rakoto@example.com'),
            $this->formRow('RABE Exemple', 'rabe@example.com', '034 00 000 02', 'Alto', ["Photo d'identité récente" => '',]),
        ]),]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.progress', ['processed' => 0, 'total' => 3, 'percent' => 0,])
            ->assertJsonPath('data.report', null)
            ->assertJsonPath('data.error', null);

        $import = UserImport::findOrFail($response->json('data.id'));
        $this->assertSame($secretary->id, $import->user_id);
        Queue::assertPushed(ImportUsersJob::class, fn (ImportUsersJob $job): bool => $job->userImportId === $import->id);
        Storage::disk('local')->assertExists($import->file_path);
        // Rien n'est créé tant que le worker n'a pas traité l'import
        $this->assertSame($usersBefore, User::count());
    }

    public function test_the_worker_imports_the_members_and_finishes_at_100_percent(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        // La file est synchrone en test : le traitement est déjà fini au retour du POST
        $this->postJson('/api/users/import', ['file' => $this->xlsx([self::HEADER, $this->validRow(),]),])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress', ['processed' => 1, 'total' => 1, 'percent' => 100,])
            ->assertJsonPath('data.report.total', 1)
            ->assertJsonPath('data.report.created', 1)
            ->assertJsonPath('data.error', null);

        $this->assertDatabaseHas('users', ['mail' => 'fanja@example.com',]);
    }

    public function test_the_uploaded_file_is_deleted_once_the_import_is_done(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $id = $this->postJson('/api/users/import', ['file' => $this->xlsx([self::HEADER, $this->validRow(),]),])
            ->assertStatus(202)
            ->json('data.id');

        $this->assertNull(UserImport::findOrFail($id)->file_path);
        Storage::disk('local')->assertDirectoryEmpty(UserImport::FILE_DIRECTORY);
    }

    public function test_a_file_that_cannot_be_read_is_refused_before_anything_is_queued(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/users/import', ['file' => $this->xlsx([['Nom', 'Téléphone',], ['Rakoto', '034',],]),])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('user_imports', 0);
        Storage::disk('local')->assertDirectoryEmpty(UserImport::FILE_DIRECTORY);
    }

    public function test_the_progress_advances_while_the_photos_are_downloaded(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $seen = null;

        // Pendant le téléchargement du lot de photos, les deux comptes sont créés mais pas les photos : 2 étapes sur 4
        $this->driveResponse = function () use (&$seen): PromiseInterface {
            $import = UserImport::firstOrFail();
            $seen ??= [$import->status->value, $import->processed, $import->total,];

            return Http::response(base64_decode(self::PNG_BASE64), 200, ['Content-Type' => 'image/png',]);
        };

        $this->postJson('/api/users/import', ['file' => $this->xlsx([
            self::FORM_HEADER,
            $this->photoRow('RAKOTO Exemple', 'rakoto@example.com'),
            $this->photoRow('RABE Exemple', 'rabe@example.com'),
        ]),])
            ->assertStatus(202)
            ->assertJsonPath('data.progress', ['processed' => 4, 'total' => 4, 'percent' => 100,]);

        $this->assertSame(['running', 2, 4,], $seen);
    }

    public function test_a_failed_photo_still_counts_as_a_finished_step(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->driveResponse = fn (): PromiseInterface => Http::response('', 404);

        $this->postJson('/api/users/import', ['file' => $this->xlsx([
            self::FORM_HEADER,
            $this->photoRow('RAKOTO Exemple', 'rakoto@example.com'),
        ]),])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress', ['processed' => 2, 'total' => 2, 'percent' => 100,])
            ->assertJsonPath('data.report.created', 1)
            ->assertJsonPath('data.report.warning_count', 1);
    }

    public function test_the_owner_can_read_the_progress_and_the_final_report(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $id = $this->postJson('/api/users/import', ['file' => $this->xlsx([self::HEADER, $this->validRow(),]),])
            ->json('data.id');

        $this->getJson('/api/users/import/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress.percent', 100)
            ->assertJsonPath('data.report.created', 1)
            ->assertJsonPath('data.report.skipped', [])
            ->assertJsonPath('data.report.errors', []);
    }

    public function test_only_the_owner_and_an_admin_can_read_an_import(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $id = $this->postJson('/api/users/import', ['file' => $this->xlsx([self::HEADER, $this->validRow(),]),])
            ->json('data.id');

        // Une autre secrétaire ne voit ni l'avancement ni les noms du rapport
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $this->getJson('/api/users/import/'.$id)->assertForbidden();

        Sanctum::actingAs($this->userWithRole(RoleName::Admin));
        $this->getJson('/api/users/import/'.$id)
            ->assertOk()
            ->assertJsonPath('data.report.created', 1);
    }

    public function test_a_member_and_a_guest_cannot_read_an_import(): void
    {
        $import = $this->importOf($this->userWithRole(RoleName::Secretary));

        $this->getJson('/api/users/import/'.$import->id)->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $this->getJson('/api/users/import/'.$import->id)->assertForbidden();
    }

    public function test_an_unknown_import_is_not_found(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->getJson('/api/users/import/999999')->assertNotFound();
        $this->getJson('/api/users/import/abc')->assertNotFound();
    }

    #[DataProvider('progressPercents')]
    public function test_the_percent_follows_the_steps_and_reaches_100_only_when_completed(
        UserImportStatus $status,
        int $total,
        int $processed,
        int $percent,
    ): void {
        $owner = $this->userWithRole(RoleName::Secretary);
        Sanctum::actingAs($owner);
        $import = $this->importOf($owner, $status, $total, $processed);

        $this->getJson('/api/users/import/'.$import->id)
            ->assertOk()
            ->assertJsonPath('data.status', $status->value)
            ->assertJsonPath('data.progress', ['processed' => $processed, 'total' => $total, 'percent' => $percent,]);
    }

    /**
     * @return array<string, array{UserImportStatus, int, int, int}>
     */
    public static function progressPercents(): array
    {
        return [
            'pending, total not known yet'             => [UserImportStatus::Pending, 0, 0, 0,],
            'pending, nothing done'                    => [UserImportStatus::Pending, 4, 0, 0,],
            'running, a quarter'                       => [UserImportStatus::Running, 4, 1, 25,],
            'running, a third is rounded down'         => [UserImportStatus::Running, 3, 1, 33,],
            'running, all steps done, report not yet'  => [UserImportStatus::Running, 4, 4, 99,],
            'completed'                                => [UserImportStatus::Completed, 4, 4, 100,],
            'completed, nothing to import'             => [UserImportStatus::Completed, 0, 0, 100,],
            'failed keeps the progress it reached'     => [UserImportStatus::Failed, 4, 2, 50,],
        ];
    }

    public function test_a_failed_job_marks_the_import_failed_and_deletes_the_file(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $id = $this->postJson('/api/users/import', ['file' => $this->xlsx([self::HEADER, $this->validRow(),]),])
            ->assertStatus(202)
            ->json('data.id');
        $path = UserImport::findOrFail($id)->file_path;
        Storage::disk('local')->assertExists($path);

        // Appelé par la file quand le Job lève une exception ou dépasse son délai
        (new ImportUsersJob($id))->failed(new RuntimeException('boom'));

        Storage::disk('local')->assertMissing($path);
        $this->getJson('/api/users/import/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.report', null)
            ->assertJsonPath(
                'data.error',
                "L'import a été interrompu par une erreur. Les membres déjà créés sont conservés : relancez le même fichier pour créer les autres.",
            );
    }

    public function test_a_finished_import_is_not_replayed_when_the_job_is_delivered_twice(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $id = $this->postJson('/api/users/import', ['file' => $this->xlsx([self::HEADER, $this->validRow(),]),])
            ->json('data.id');
        $job = new ImportUsersJob($id);

        $job->handle(app(RunUsersImportAction::class));
        $usersAfterFirstRun = User::count();
        $report = UserImport::findOrFail($id)->report;

        // Livré une seconde fois : rien ne change, et le fichier supprimé n'est pas relu
        $job->handle(app(RunUsersImportAction::class));

        $this->assertSame($usersAfterFirstRun, User::count());
        $this->assertSame($report, UserImport::findOrFail($id)->report);
        $this->assertSame(1, $report['created']);
    }

    public function test_the_job_is_a_single_long_attempt_that_the_queue_does_not_hand_out_twice(): void
    {
        $job = new ImportUsersJob(1);

        $this->assertSame(1, $job->tries);
        $this->assertTrue($job->failOnTimeout);
        // Sinon un second worker reprendrait l'import encore en cours
        $this->assertLessThan((int) config('queue.connections.database.retry_after'), $job->timeout);
    }

    // ── Outils ──────────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    private function validRow(string $mail = 'fanja@example.com', string $firstName = 'Fanja'): array
    {
        return ['Rakoto', $firstName, 'S', '034 12 345 67', $mail,];
    }

    /**
     * Une réponse du formulaire de recensement, dans l'ordre de FORM_HEADER.
     *
     * @param  array<string, string>  $overrides  valeurs à changer, indexées par l'en-tête de la colonne
     * @return list<string>
     */
    private function formRow(string $name, string $mail, string $phone, string $voice, array $overrides = []): array
    {
        $row = [
            '2026-11-03 19:42:10',
            $name,
            $mail,
            'Lot IVB 12 Faravohitra, Antananarivo',
            $phone,
            '12/03/1998 à Antananarivo',
            $voice,
            'Pseudo Exemple',
            '1234 Faravohitra',
            '05/06/1998',
            'Ecar Faravohitra',
            '14/05/2008',
            'Ecar Faravohitra',
            '15 août 2012',
            'Cathédrale Andohalo',
            '',
            '',
            '',
            'Septembre 2019',
            'Ecar Faravohitra',
            '08/12/2020',
            'Parents',
            'RAKOTO Papa, RAKOTO Maman',
            '034 00 000 02 / 033 00 000 03',
            'https://drive.google.com/open?id=exemple1',
        ];

        return array_values(array_merge(array_combine(self::FORM_HEADER, $row), $overrides));
    }

    /**
     * Une réponse dont toutes les dates sont lisibles : le seul avertissement possible est celui de la photo.
     *
     * @return list<string>
     */
    private function photoRow(string $name, string $mail, string $picture = 'https://drive.google.com/open?id=exemple1'): array
    {
        return $this->formRow($name, $mail, '034 00 000 01', 'Ténor', [
            "Date d'adhésion à la chorale" => '03/09/2019',
            "Photo d'identité récente"     => $picture,
        ]);
    }

    /**
     * Réponses du formulaire que l'import ne sait pas lire : la ligne est importée sans elles.
     *
     * @return array<string, string>
     */
    private function unreadableAnswers(): array
    {
        return [
            'Date et Lieu de naissance'                                  => '1995, Fianarantsoa',
            "Date d'adhésion à la chorale"                               => 'Septembre 2019',
            "Date d'engagement"                                          => 'Je ne sais plus',
            'Date du Mariage'                                            => '31/02/2020',
            "Photo d'identité récente"                                   => 'pas de photo',
            "Nom de la (ou des) personne(s) à contacter en cas d'urgence" => '',
            "A contacter en cas d'urgence"                               => '',
        ];
    }

    /**
     * @param  list<list<mixed>>  $rows  première ligne = en-têtes ; chaque entrée devient une ligne Excel, vides comprises
     */
    private function previewFile(array $rows): TestResponse
    {
        return $this->postJson('/api/users/import/preview', ['file' => $this->xlsx($rows),]);
    }

    /**
     * Lance l'import et renvoie son rapport final dans `data`. La file est synchrone en test : au retour du POST le
     * traitement est fini. Un refus (422, 403, 401) est renvoyé tel quel.
     *
     * @param  list<list<mixed>>  $rows
     */
    private function importFile(array $rows): TestResponse
    {
        $response = $this->postJson('/api/users/import', ['file' => $this->xlsx($rows),]);

        if ($response->status() !== 202) {
            return $response;
        }

        $response->assertJsonPath('data.status', 'completed');

        return TestResponse::fromBaseResponse(new JsonResponse(['data' => $response->json('data.report'),]));
    }

    private function importOf(
        User $owner,
        UserImportStatus $status = UserImportStatus::Pending,
        int $total = 0,
        int $processed = 0,
    ): UserImport {
        return UserImport::create([
            'user_id'   => $owner->id,
            'status'    => $status,
            'total'     => $total,
            'processed' => $processed,
        ]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import');
        $writer = new Writer();
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return UploadedFile::fake()->createWithContent('membres-cff.xlsx', (string) file_get_contents($path));
    }

    /**
     * @return list<list<mixed>> lignes de la première feuille
     */
    private function readWorkbook(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'workbook');
        file_put_contents($path, $content);
        $reader = new Reader(new ReaderOptions());
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * Un membre déjà inscrit, avec ses prénoms (le premier est le prénom par défaut).
     *
     * @param  list<string>  $firstNames
     */
    private function existingMember(string $mail, string $lastName, array $firstNames): User
    {
        $member = User::factory()->create(['mail' => $mail, 'last_name' => $lastName,]);

        foreach ($firstNames as $order => $firstName) {
            Firstname::create([
                'user_id'    => $member->id,
                'first_name' => $firstName,
                'order'      => $order,
                'default'    => $order === 0,
            ]);
        }

        return $member;
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
