<?php

namespace Tests\Feature\Member;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Firstname;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Voice;
use App\Models\VoiceGroup;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class ExportMembersTest extends TestCase
{
    use RefreshDatabase;

    private const array HEADER = ['Nom', 'Prénoms', 'Voix', 'Voix précise', 'Téléphone', 'Email',];

    private const string CSV_URL = '/api/members/export?format=csv';

    private const string XLSX_URL = '/api/members/export?format=xlsx';

    private const string XLSX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @var list<string> Fichiers xlsx téléchargés pendant le test, supprimés à la fin */
    private array $downloads = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([VoiceSeeder::class, RoleSeeder::class,]);
        $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->downloads);

        parent::tearDown();
    }

    // ── CSV ─────────────────────────────────────────────────────────────

    public function test_a_secretary_can_export_the_members_as_csv(): void
    {
        $this->actingAsRole(RoleName::Secretary);

        $this->getJson(self::CSV_URL)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('membres-cff-2026-10-27.csv');
    }

    public function test_an_admin_can_export_the_members_as_csv(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $this->getJson(self::CSV_URL)->assertOk()->assertDownload('membres-cff-2026-10-27.csv');
    }

    public function test_the_csv_lists_the_members_with_the_expected_columns(): void
    {
        $this->member('Ravélo', 'Hélène Marie', 'S', 'S1', '034 12 345 67', ['mail' => 'helene@example.com',]);
        $this->actingAsRole(RoleName::Secretary);

        $rows = $this->csvRows(self::CSV_URL);

        $this->assertSame(self::HEADER, $rows[0]);
        $this->assertSame(
            ['Ravélo', 'Hélène Marie', 'S', 'S1', '034 12 345 67', 'helene@example.com',],
            $rows[1],
        );
    }

    public function test_the_csv_is_opened_correctly_by_excel(): void
    {
        $this->member('Ravélo', 'Hélène', 'S');
        $this->actingAsRole(RoleName::Secretary);

        $content = $this->getJson(self::CSV_URL)->streamedContent();

        // BOM UTF-8, séparateur « ; » et accents conservés
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Nom;Prénoms;Voix;', $content);
        $this->assertStringContainsString('Ravélo;Hélène;S;;', $content);
        $this->assertTrue(mb_check_encoding($content, 'UTF-8'));
    }

    public function test_the_members_are_sorted_by_last_name(): void
    {
        $this->member('Rakoto', 'Tojo', 'T');
        $this->member('Andry', 'Jean', 'S');
        $this->member('Mamy', 'Lova', 'A');
        $this->actingAsRole(RoleName::Secretary);

        $this->assertSame(
            ['Andry', 'Mamy', 'Rakoto', 'Zafy',],
            $this->lastNames($this->csvRows(self::CSV_URL)),
        );
    }

    public function test_a_member_without_profile_is_exported_with_empty_voice_and_phone(): void
    {
        $secretary = $this->actingAsRole(RoleName::Secretary);

        $rows = $this->csvRows(self::CSV_URL);

        $this->assertSame(['Zafy', '', '', '', '', $secretary->mail,], $rows[1]);
    }

    public function test_pending_accounts_are_not_exported(): void
    {
        $this->member('Andry', 'Jean', 'S');
        $this->member('Attente', 'Lova', 'S', attributes: ['status' => UserStatus::Pending,]);
        $this->actingAsRole(RoleName::Secretary);

        $this->assertSame(['Andry', 'Zafy',], $this->lastNames($this->csvRows(self::CSV_URL)));
    }

    public function test_the_super_admin_account_is_not_exported(): void
    {
        config(['kantica.super_admin.mail' => 'superadmin@example.com',]);
        $superAdmin = User::factory()->create(['mail' => 'superadmin@example.com', 'last_name' => null,]);
        $superAdmin->assignRole(RoleName::Admin->value);
        $this->member('Andry', 'Jean', 'S');
        $this->actingAsRole(RoleName::Admin);

        $rows = $this->csvRows(self::CSV_URL);

        // Les autres admins restent des membres exportés
        $this->assertSame(['Andry', 'Zafy',], $this->lastNames($rows));
        $this->assertNotContains('superadmin@example.com', array_column($rows, 5));
    }

    public function test_no_account_is_excluded_when_no_super_admin_mail_is_configured(): void
    {
        config(['kantica.super_admin.mail' => null,]);
        User::factory()->create(['mail' => 'superadmin@example.com', 'last_name' => 'Andry',]);
        $this->actingAsRole(RoleName::Admin);

        $this->assertSame(['Andry', 'Zafy',], $this->lastNames($this->csvRows(self::CSV_URL)));
    }

    public function test_values_typed_by_members_cannot_be_read_as_formulas(): void
    {
        $this->member('=SUM(A1)', '@cmd', 'S', phone: '+1+1', attributes: ['mail' => 'formula@example.com',]);
        $this->member('Andry', 'Jean', 'S', phone: '+261 34 00 000 00', attributes: ['mail' => 'phone@example.com',]);
        $this->actingAsRole(RoleName::Secretary);

        $rows = array_column($this->csvRows(self::CSV_URL), null, 5);

        $this->assertSame("'=SUM(A1)", $rows['formula@example.com'][0]);
        $this->assertSame("'@cmd", $rows['formula@example.com'][1]);
        $this->assertSame("'+1+1", $rows['formula@example.com'][4]);
        // Un vrai numéro de téléphone commençant par « + » reste intact
        $this->assertSame('+261 34 00 000 00', $rows['phone@example.com'][4]);
    }

    // ── Filtre de voix ──────────────────────────────────────────────────

    public function test_the_voice_filter_limits_the_export_to_that_voice(): void
    {
        $this->member('Andry', 'Jean', 'S');
        $this->member('Mamy', 'Lova', 'A', 'A1');
        $this->member('Rakoto', 'Tojo', 'A', 'A2');
        $this->member('Soa', 'Hery', 'T');
        $this->actingAsRole(RoleName::Secretary);

        $rows = $this->csvRows(self::CSV_URL.'&voice=A');

        $this->assertSame(['Mamy', 'Rakoto',], $this->lastNames($rows));
    }

    public function test_every_member_is_exported_without_voice_filter(): void
    {
        $this->member('Andry', 'Jean', 'S');
        $this->member('Mamy', 'Lova', 'A');
        $this->member('Rakoto', 'Tojo', 'T');
        $this->member('Soa', 'Hery', 'B');
        $this->actingAsRole(RoleName::Secretary);

        $this->assertSame(
            ['Andry', 'Mamy', 'Rakoto', 'Soa', 'Zafy',],
            $this->lastNames($this->csvRows(self::CSV_URL)),
        );
    }

    public function test_an_empty_voice_filter_exports_every_voice(): void
    {
        $this->member('Andry', 'Jean', 'S');
        $this->member('Mamy', 'Lova', 'A');
        $this->actingAsRole(RoleName::Secretary);

        $this->assertSame(
            ['Andry', 'Mamy', 'Zafy',],
            $this->lastNames($this->csvRows(self::CSV_URL.'&voice=')),
        );
    }

    public function test_a_voice_without_member_gives_a_csv_with_only_the_header(): void
    {
        $this->member('Andry', 'Jean', 'S');
        $this->actingAsRole(RoleName::Secretary);

        $rows = $this->csvRows(self::CSV_URL.'&voice=B');

        $this->assertSame([self::HEADER,], $rows);
    }

    // ── Excel (xlsx) ────────────────────────────────────────────────────

    public function test_a_secretary_can_export_the_members_as_xlsx(): void
    {
        $this->actingAsRole(RoleName::Secretary);

        $response = $this->getJson(self::XLSX_URL)
            ->assertOk()
            ->assertHeader('Content-Type', self::XLSX_CONTENT_TYPE)
            ->assertDownload('membres-cff-2026-10-27.xlsx');

        // Un fichier xlsx est une archive zip
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_an_admin_can_export_the_members_as_xlsx(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $this->getJson(self::XLSX_URL)->assertOk()->assertDownload('membres-cff-2026-10-27.xlsx');
    }

    public function test_the_xlsx_lists_the_members_with_the_expected_columns(): void
    {
        $this->member('Ravélo', 'Hélène Marie', 'S', 'S1', '034 12 345 67', ['mail' => 'helene@example.com',]);
        $this->actingAsRole(RoleName::Secretary);

        $rows = $this->xlsxRows(self::XLSX_URL);

        $this->assertSame(self::HEADER, $rows[0]);
        $this->assertSame(
            ['Ravélo', 'Hélène Marie', 'S', 'S1', '034 12 345 67', 'helene@example.com',],
            $rows[1],
        );
    }

    public function test_the_xlsx_has_a_single_sheet_named_members(): void
    {
        $this->actingAsRole(RoleName::Secretary);

        $zip = new ZipArchive();
        $zip->open($this->downloadXlsx(self::XLSX_URL));

        $this->assertStringContainsString('name="Membres"', $zip->getFromName('xl/workbook.xml'));
        $this->assertFalse($zip->getFromName('xl/worksheets/sheet2.xml'));
    }

    public function test_the_xlsx_applies_the_same_rules_as_the_csv(): void
    {
        $this->member('Rakoto', 'Tojo', 'A', 'A2');
        $this->member('Andry', 'Jean', 'S');
        $this->member('Mamy', 'Lova', 'A', 'A1');
        $this->member('Attente', 'Sarobidy', 'A', attributes: ['status' => UserStatus::Pending,]);
        $this->actingAsRole(RoleName::Secretary);

        // Tri par nom, comptes en attente exclus
        $this->assertSame(
            ['Andry', 'Mamy', 'Rakoto', 'Zafy',],
            $this->lastNames($this->xlsxRows(self::XLSX_URL)),
        );
        // Filtre de voix
        $this->assertSame(
            ['Mamy', 'Rakoto',],
            $this->lastNames($this->xlsxRows(self::XLSX_URL.'&voice=A')),
        );
    }

    public function test_values_typed_by_members_are_never_formulas_in_the_xlsx(): void
    {
        $this->member('=SUM(A1)', '@cmd', 'S', phone: '+1+1', attributes: ['mail' => 'formula@example.com',]);
        $this->actingAsRole(RoleName::Secretary);
        $path = $this->downloadXlsx(self::XLSX_URL);

        // Les valeurs sont conservées telles quelles : ce sont des textes, pas des formules
        $rows = array_column($this->readXlsx($path), null, 5);
        $this->assertSame('=SUM(A1)', $rows['formula@example.com'][0]);
        $this->assertSame('@cmd', $rows['formula@example.com'][1]);
        $this->assertSame('+1+1', $rows['formula@example.com'][4]);

        $zip = new ZipArchive();
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('=SUM(A1)', $sheet);
        $this->assertDoesNotMatchRegularExpression('/<f[\s>]/', $sheet);
    }

    // ── PDF ─────────────────────────────────────────────────────────────

    public function test_a_secretary_can_export_the_members_as_pdf(): void
    {
        $this->member('Ravélo', 'Hélène', 'S');
        $this->actingAsRole(RoleName::Secretary);

        $response = $this->getJson('/api/members/export?format=pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('membres-cff-2026-10-27.pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_an_admin_can_export_the_members_as_pdf(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $this->getJson('/api/members/export?format=pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_pdf_recalls_the_exported_scope(): void
    {
        $scopes = [];
        View::composer('exports.members', function ($view) use (&$scopes): void {
            $scopes[] = $view->getData()['scope'];
        });
        $this->member('Andry', 'Jean', 'S');
        $this->member('Mamy', 'Lova', 'A');
        $this->actingAsRole(RoleName::Secretary);

        $this->getJson('/api/members/export?format=pdf')->assertOk();
        $this->getJson('/api/members/export?format=pdf&voice=A')->assertOk();

        $this->assertSame(['3 choristes · toutes les voix', '1 choriste · voix A',], $scopes);
    }

    public function test_the_pdf_view_lists_the_members_and_escapes_their_values(): void
    {
        $this->view('exports.members', [
            'columns'    => self::HEADER,
            'rows'       => [['Ravélo', '<script>alert(1)</script>', 'S', 'S1', '034 12 345 67', 'helene@example.com',],],
            'scope'      => '1 choriste · toutes les voix',
            'exportedAt' => now(),
        ])
            ->assertSeeInOrder([
                'Chorale Francophone de Faravohitra',
                'Liste des membres',
                '1 choriste · toutes les voix',
                'exportée le 27/10/2026',
                'Ravélo',
            ])
            ->assertDontSee('<script>', false)
            ->assertDontSee('Aucun membre à exporter.');
    }

    public function test_the_pdf_view_handles_an_empty_list(): void
    {
        $this->view('exports.members', [
            'columns'    => self::HEADER,
            'rows'       => [],
            'scope'      => '0 choriste · voix B',
            'exportedAt' => now(),
        ])->assertSee('Aucun membre à exporter.');
    }

    // ── Autorisations ───────────────────────────────────────────────────

    public function test_a_member_cannot_export_the_members(): void
    {
        $this->actingAsRole(RoleName::Member);

        $this->getJson(self::CSV_URL)->assertForbidden();
        $this->getJson(self::XLSX_URL)->assertForbidden();
        $this->getJson('/api/members/export?format=pdf')->assertForbidden();
        // La Policy passe avant la validation de la requête
        $this->getJson('/api/members/export')->assertForbidden();
    }

    public function test_a_guest_cannot_export_the_members(): void
    {
        $this->getJson(self::CSV_URL)->assertUnauthorized();
    }

    public function test_a_pending_secretary_cannot_export_the_members(): void
    {
        $secretary = User::factory()->pending()->create();
        $secretary->assignRole(RoleName::Secretary->value);
        Sanctum::actingAs($secretary);

        $this->getJson(self::CSV_URL)
            ->assertForbidden()
            ->assertJsonPath('code', EnsureAccountIsActive::ERROR_CODE);
    }

    // ── Validation ──────────────────────────────────────────────────────

    public function test_the_format_is_required(): void
    {
        $this->actingAsRole(RoleName::Secretary);

        $this->getJson('/api/members/export')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format' => 'Le format d\'export est obligatoire.',]);
    }

    public function test_an_unknown_format_is_refused(): void
    {
        $this->actingAsRole(RoleName::Secretary);

        $this->getJson('/api/members/export?format=docx')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format' => 'Le format d\'export doit être csv, xlsx ou pdf.',]);
    }

    public function test_an_unknown_voice_is_refused(): void
    {
        $this->actingAsRole(RoleName::Secretary);

        $this->getJson(self::CSV_URL.'&voice=X')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voice' => 'La voix doit être S, A, T ou B.',]);
    }

    // ── Aides ───────────────────────────────────────────────────────────

    // Compte actif sans fiche : il apparaît dans l'export sous le nom « Zafy »
    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create(['last_name' => 'Zafy',]);
        $user->assignRole($role->value);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(
        string $lastName,
        string $firstNames,
        string $voiceGroup,
        ?string $voice = null,
        string $phone = '034 00 000 00',
        array $attributes = [],
    ): User {
        $user = User::factory()->create(['last_name' => $lastName, ...$attributes,]);
        $user->assignRole(RoleName::Member->value);

        foreach (explode(' ', $firstNames) as $order => $firstName) {
            Firstname::create([
                'user_id'    => $user->id,
                'first_name' => $firstName,
                'order'      => $order,
                'default'    => $order === 0,
            ]);
        }

        $group = VoiceGroup::where('abbreviation', $voiceGroup)->firstOrFail();

        UserProfile::create([
            'user_id'        => $user->id,
            'voice_group_id' => $group->id,
            'voice_id'       => $voice === null
                ? null
                : Voice::where('vocal_group_id', $group->id)->where('name', $voice)->firstOrFail()->id,
            'phone'          => $phone,
        ]);

        return $user;
    }

    /**
     * Télécharge le CSV, vérifie le BOM et retourne toutes les lignes (en-têtes comprises).
     *
     * @return list<list<string|null>>
     */
    private function csvRows(string $url): array
    {
        $content = $this->getJson($url)->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = array_values(array_filter(explode("\r\n", substr($content, 3)), fn (string $line) => $line !== ''));

        return array_map(fn (string $line) => str_getcsv($line, ';', '"', ''), $lines);
    }

    /**
     * Télécharge le fichier Excel dans un fichier temporaire (supprimé en fin de test) et retourne son chemin.
     */
    private function downloadXlsx(string $url): string
    {
        $content = $this->getJson($url)->assertOk()->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'members-');
        file_put_contents($path, $content);
        $this->downloads[] = $path;

        return $path;
    }

    /**
     * Lit la première feuille d'un fichier Excel : toutes les lignes (en-têtes comprises), 6 colonnes de texte.
     *
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        $rows = [];
        $reader = new Reader();
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($value): string => (string) $value, $row->toArray());
                $rows[] = array_pad($values, count(self::HEADER), '');
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function xlsxRows(string $url): array
    {
        return $this->readXlsx($this->downloadXlsx($url));
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @return list<string|null>
     */
    private function lastNames(array $rows): array
    {
        return array_column(array_slice($rows, 1), 0);
    }
}
