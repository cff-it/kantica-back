<?php

namespace Tests\Feature\Console;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Voice;
use App\Models\VoiceGroup;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeployCommandTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN_MAIL = 'admin@example.com';

    private const string ADMIN_PASSWORD = 'Password123!';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kantica.super_admin.mail'     => self::ADMIN_MAIL,
            'kantica.super_admin.password' => self::ADMIN_PASSWORD,
        ]);
    }

    public function test_it_creates_the_reference_data(): void
    {
        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            array_column(RoleName::cases(), 'value'),
            Role::pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Soprano' => 'S', 'Alto' => 'A', 'Tenor' => 'T', 'Bass' => 'B',],
            VoiceGroup::pluck('abbreviation', 'name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['S1', 'S2', 'A1', 'A2', 'T1', 'T2', 'B1', 'B2',],
            Voice::pluck('name')->all(),
        );
    }

    public function test_each_voice_belongs_to_its_group(): void
    {
        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();

        foreach (VoiceGroup::with('voices')->get() as $group) {
            $this->assertEqualsCanonicalizing(
                [$group->abbreviation.'1', $group->abbreviation.'2',],
                $group->voices->pluck('name')->all(),
            );
        }
    }

    public function test_running_it_twice_creates_no_duplicates(): void
    {
        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();
        $this->artisan('app:deploy', ['--skip-migrate' => true,])
            ->expectsOutputToContain('0 créé(s) ou mis à jour')
            ->assertSuccessful();

        $this->assertSame(count(RoleName::cases()), Role::count());
        $this->assertSame(4, VoiceGroup::count());
        $this->assertSame(8, Voice::count());
    }

    public function test_it_renames_a_group_stored_with_its_abbreviation_only(): void
    {
        VoiceGroup::create(['name' => 'S', 'abbreviation' => 'S',]);

        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();

        $this->assertSame(4, VoiceGroup::count());
        $this->assertSame('Soprano', VoiceGroup::where('abbreviation', 'S')->value('name'));
    }

    public function test_it_runs_the_migrations_by_default(): void
    {
        $this->artisan('app:deploy')
            ->expectsOutputToContain('Données de référence')
            ->assertSuccessful();
    }

    public function test_it_creates_the_original_admin(): void
    {
        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();

        $admin = User::where('mail', self::ADMIN_MAIL)->firstOrFail();

        $this->assertTrue($admin->hasRole(RoleName::Admin->value));
        $this->assertSame(UserStatus::Active, $admin->status);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check(self::ADMIN_PASSWORD, $admin->password));
    }

    public function test_an_existing_admin_is_never_modified(): void
    {
        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();
        $admin = User::where('mail', self::ADMIN_MAIL)->firstOrFail();
        $admin->update(['password' => 'Changé123!',]);

        config(['kantica.super_admin.password' => 'Autre456!',]);
        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();

        $this->assertSame(1, User::role(RoleName::Admin->value)->count());
        $this->assertTrue(Hash::check('Changé123!', $admin->refresh()->password));
    }

    public function test_no_variable_is_needed_once_an_admin_exists(): void
    {
        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();

        config(['kantica.super_admin.mail' => null, 'kantica.super_admin.password' => null,]);

        $this->artisan('app:deploy', ['--skip-migrate' => true,])->assertSuccessful();
    }

    public function test_it_fails_when_no_admin_exists_and_variables_are_missing(): void
    {
        config(['kantica.super_admin.mail' => null,]);

        $this->artisan('app:deploy', ['--skip-migrate' => true,])
            ->expectsOutputToContain('SUPER_ADMIN_MAIL')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_a_weak_password_is_refused(): void
    {
        config(['kantica.super_admin.password' => 'password123!',]);

        $this->artisan('app:deploy', ['--skip-migrate' => true,])
            ->expectsOutputToContain('SUPER_ADMIN_PASSWORD doit contenir au moins une majuscule et une minuscule.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['mail' => self::ADMIN_MAIL,]);
    }

    public function test_an_existing_non_admin_account_is_not_promoted(): void
    {
        User::factory()->create(['mail' => self::ADMIN_MAIL,]);
        $this->seed(RoleSeeder::class);

        $this->artisan('app:deploy', ['--skip-migrate' => true,])
            ->expectsOutputToContain('existe déjà sans le rôle admin')
            ->assertFailed();

        $this->assertSame(0, User::role(RoleName::Admin->value)->count());
    }
}
