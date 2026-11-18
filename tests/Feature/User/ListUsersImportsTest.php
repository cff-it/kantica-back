<?php

namespace Tests\Feature\User;

use App\Enums\RoleName;
use App\Enums\UserImportStatus;
use App\Models\User;
use App\Models\UserImport;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

// Historique des imports (KAN-31) : `GET /api/users/import` (liste) et `GET /api/users/import/{id}` (détail)
class ListUsersImportsTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    private const array REPORT = [
        'total'           => 5,
        'created'         => 3,
        'skipped_count'   => 1,
        'error_count'     => 1,
        'warning_count'   => 2,
        'skipped'         => [['line' => 4, 'name' => 'RAKOTO Lova', 'message' => 'Déjà inscrit.',],],
        'errors'          => [],
        'warnings'        => [],
        'ignored_columns' => [],
    ];

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->secretary = $this->member('Rakoto', 'Fanja', null, role: RoleName::Secretary);
    }

    public function test_a_secretary_lists_her_imports_from_the_newest_to_the_oldest(): void
    {
        $oldest = $this->importOf($this->secretary, createdAt: CarbonImmutable::parse('2026-11-10 08:00:00'));
        $newest = $this->importOf($this->secretary, createdAt: CarbonImmutable::parse('2026-11-12 08:00:00'));
        $middle = $this->importOf($this->secretary, createdAt: CarbonImmutable::parse('2026-11-11 08:00:00'));
        Sanctum::actingAs($this->secretary);

        $response = $this->getJson('/api/users/import')->assertOk();

        $this->assertSame([$newest->id, $middle->id, $oldest->id,], $response->json('data.*.id'));
    }

    public function test_a_row_carries_the_author_the_progress_the_dates_and_the_summary(): void
    {
        $createdAt = CarbonImmutable::parse('2026-11-10 08:30:00');
        $finishedAt = CarbonImmutable::parse('2026-11-10 08:31:00');
        $import = $this->importOf(
            $this->secretary,
            UserImportStatus::Completed,
            total: 4,
            processed: 4,
            report: self::REPORT,
            createdAt: $createdAt,
            updatedAt: $finishedAt,
        );
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/users/import')
            ->assertOk()
            ->assertJsonPath('data.0', [
                'id'          => $import->id,
                'status'      => 'completed',
                'user'        => ['id' => $this->secretary->id, 'last_name' => 'Rakoto', 'first_name' => 'Fanja',],
                'created_at'  => $createdAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'progress'    => ['processed' => 4, 'total' => 4, 'percent' => 100,],
                'error'       => null,
                'summary'     => [
                    'total'         => 5,
                    'created'       => 3,
                    'skipped_count' => 1,
                    'error_count'   => 1,
                    'warning_count' => 2,
                ],
            ])
            // Les lignes du rapport (noms des membres) restent dans le détail
            ->assertJsonMissingPath('data.0.report');
    }

    public function test_a_running_import_has_its_percent_and_no_finish_date_nor_summary(): void
    {
        $this->importOf($this->secretary, UserImportStatus::Running, total: 4, processed: 2);
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/users/import')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'running')
            ->assertJsonPath('data.0.progress', ['processed' => 2, 'total' => 4, 'percent' => 50,])
            ->assertJsonPath('data.0.finished_at', null)
            ->assertJsonPath('data.0.summary', null);
    }

    public function test_a_failed_import_carries_its_error_and_a_finish_date_but_no_summary(): void
    {
        $this->importOf($this->secretary, UserImportStatus::Failed, total: 4, processed: 1, error: 'Panne du worker.');
        Sanctum::actingAs($this->secretary);

        $response = $this->getJson('/api/users/import')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.error', 'Panne du worker.')
            ->assertJsonPath('data.0.summary', null);

        $this->assertNotNull($response->json('data.0.finished_at'));
    }

    public function test_a_secretary_sees_only_her_own_imports(): void
    {
        $mine = $this->importOf($this->secretary);
        $this->importOf($this->member('Andria', 'Lova', null, role: RoleName::Secretary));
        Sanctum::actingAs($this->secretary);

        $response = $this->getJson('/api/users/import')->assertOk();

        $this->assertSame([$mine->id,], $response->json('data.*.id'));
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_an_admin_sees_every_import_with_its_author(): void
    {
        $first = $this->importOf($this->secretary, createdAt: CarbonImmutable::parse('2026-11-10 08:00:00'));
        $other = $this->member('Andria', 'Lova', null, role: RoleName::Secretary);
        $second = $this->importOf($other, createdAt: CarbonImmutable::parse('2026-11-11 08:00:00'));
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $response = $this->getJson('/api/users/import')->assertOk();

        $this->assertSame([$second->id, $first->id,], $response->json('data.*.id'));
        $this->assertSame(['Andria', 'Rakoto',], $response->json('data.*.user.last_name'));
    }

    public function test_the_list_is_empty_without_any_import(): void
    {
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/users/import')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_the_list_is_paginated_by_twenty(): void
    {
        $imports = collect(range(1, 21))->map(fn () => $this->importOf($this->secretary));
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/users/import')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.per_page', 20);

        // Page 2 : le tout premier import, le plus ancien
        $this->getJson('/api/users/import?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.id', $imports->first()->id);
    }

    #[DataProvider('invalidPages')]
    public function test_an_invalid_page_is_refused(string $page): void
    {
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/users/import?page='.$page)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    public static function invalidPages(): array
    {
        return [
            'zero'     => ['0',],
            'negative' => ['-1',],
            'text'     => ['abc',],
        ];
    }

    public function test_a_member_cannot_list_the_imports(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Member));

        $this->getJson('/api/users/import')->assertForbidden();
    }

    public function test_a_guest_cannot_list_the_imports(): void
    {
        $this->getJson('/api/users/import')->assertUnauthorized();
    }

    public function test_the_list_does_not_query_each_import_separately(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));
        $this->importOf($this->secretary);

        // Premier appel hors mesure : il charge les rôles de l'utilisateur connecté
        $this->getJson('/api/users/import')->assertOk();

        DB::enableQueryLog();
        $this->getJson('/api/users/import')->assertOk();
        $queriesForOneImport = count(DB::getQueryLog());

        foreach (['Andria', 'Razafy', 'Randria', 'Rabe',] as $lastName) {
            $this->importOf($this->member($lastName, 'Lova', null, role: RoleName::Secretary));
        }

        DB::flushQueryLog();
        $this->getJson('/api/users/import')->assertOk()->assertJsonCount(5, 'data');

        $this->assertSame($queriesForOneImport, count(DB::getQueryLog()));
    }

    public function test_the_detail_carries_the_author_the_dates_and_the_full_report(): void
    {
        $createdAt = CarbonImmutable::parse('2026-11-10 08:30:00');
        $finishedAt = CarbonImmutable::parse('2026-11-10 08:31:00');
        $import = $this->importOf(
            $this->secretary,
            UserImportStatus::Completed,
            total: 4,
            processed: 4,
            report: self::REPORT,
            createdAt: $createdAt,
            updatedAt: $finishedAt,
        );
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/users/import/'.$import->id)
            ->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.user', ['id' => $this->secretary->id, 'last_name' => 'Rakoto', 'first_name' => 'Fanja',])
            ->assertJsonPath('data.created_at', $createdAt->toIso8601String())
            ->assertJsonPath('data.finished_at', $finishedAt->toIso8601String())
            ->assertJsonPath('data.report.created', 3)
            ->assertJsonPath('data.report.skipped.0.name', 'RAKOTO Lova');
    }

    public function test_the_detail_has_no_finish_date_while_the_import_is_running(): void
    {
        $import = $this->importOf($this->secretary, UserImportStatus::Running, total: 4, processed: 1);
        Sanctum::actingAs($this->secretary);

        $this->getJson('/api/users/import/'.$import->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.finished_at', null)
            ->assertJsonPath('data.report', null);
    }

    public function test_an_admin_reads_the_detail_of_an_import_started_by_a_secretary(): void
    {
        $import = $this->importOf($this->secretary, UserImportStatus::Completed, total: 4, processed: 4, report: self::REPORT);
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->getJson('/api/users/import/'.$import->id)
            ->assertOk()
            ->assertJsonPath('data.user.last_name', 'Rakoto');
    }

    public function test_another_secretary_cannot_read_the_detail(): void
    {
        $import = $this->importOf($this->secretary);
        Sanctum::actingAs($this->member('Andria', 'Lova', null, role: RoleName::Secretary));

        $this->getJson('/api/users/import/'.$import->id)->assertForbidden();
    }

    /**
     * @param  array<string, mixed>|null  $report
     */
    private function importOf(
        User $owner,
        UserImportStatus $status = UserImportStatus::Pending,
        int $total = 0,
        int $processed = 0,
        ?array $report = null,
        ?string $error = null,
        ?CarbonImmutable $createdAt = null,
        ?CarbonImmutable $updatedAt = null,
    ): UserImport {
        $import = UserImport::create([
            'user_id'       => $owner->id,
            'status'        => $status,
            'total'         => $total,
            'processed'     => $processed,
            'report'        => $report,
            'error_message' => $error,
        ]);

        if ($createdAt !== null) {
            $import->forceFill(['created_at' => $createdAt, 'updated_at' => $updatedAt ?? $createdAt,])->save();
        }

        return $import;
    }
}
