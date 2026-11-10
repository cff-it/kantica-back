<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Firstname;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Voice;
use App\Models\VoiceGroup;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Base des jeux de données alignés sur les scénarios du plan de tests KAN-33
 * (session-tracking/Lot-1/kan-33/jeux-de-donnees.md).
 *
 * Chaque jeu est autonome : il remplace le précédent. Seuls les comptes du domaine réservé MAIL_DOMAIN sont
 * supprimés, jamais un vrai compte. Tous les comptes ont le mot de passe `password` (celui de UserFactory) :
 * ces jeux ne se lancent donc pas en production.
 */
abstract class MemberScenarioSeeder extends Seeder
{
    public const string MAIL_DOMAIN = 'scenarios.kantica.test';

    /** @var list<array<int, int|string>> */
    private array $rows = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Les jeux de données de scénarios ne se lancent pas en production : leurs comptes ont un mot de passe connu.',
            );
        }

        $this->call([RoleSeeder::class, VoiceSeeder::class,]);

        $this->purge();
        $this->members();
        $this->report();
    }

    /**
     * Crée les comptes du jeu, avec $this->member().
     */
    abstract protected function members(): void;

    /**
     * Compte actif et vérifié (sauf $status) avec son rôle, ses prénoms et, si un groupe de voix est donné, sa fiche
     * de recensement. $firstNames : un mot = une ligne de `firstnames`, le premier est le prénom par défaut.
     * $voiceGroup : S, A, T ou B ; null = membre sans fiche. $voice : S1, A2... (facultative, du groupe choisi).
     */
    protected function member(
        string $account,
        ?string $lastName,
        ?string $firstNames,
        ?string $voiceGroup,
        ?string $voice = null,
        RoleName $role = RoleName::Member,
        UserStatus $status = UserStatus::Active,
        string $phone = '034 12 345 67',
    ): User {
        $user = User::factory()->create([
            'mail'      => $account.'@'.self::MAIL_DOMAIN,
            'last_name' => $lastName,
            'status'    => $status,
        ]);
        $user->assignRole($role->value);

        $words = preg_split('/\s+/', trim((string) $firstNames), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $order => $word) {
            Firstname::create([
                'user_id'    => $user->id,
                'first_name' => $word,
                'order'      => $order,
                'default'    => $order === 0,
            ]);
        }

        if ($voiceGroup !== null) {
            $group = VoiceGroup::where('abbreviation', $voiceGroup)->firstOrFail();
            $voiceId = $voice === null
                ? null
                : Voice::where('vocal_group_id', $group->id)->where('name', $voice)->firstOrFail()->id;

            UserProfile::create([
                'user_id'        => $user->id,
                'voice_group_id' => $group->id,
                'voice_id'       => $voiceId,
                'phone'          => $phone,
            ]);
        }

        $this->rows[] = [
            $user->id,
            $user->mail,
            $lastName ?? '(sans nom)',
            implode(' ', $words),
            $role->value,
            $status->value,
            $voice ?? $voiceGroup ?? '(sans fiche)',
        ];

        return $user;
    }

    // Retire les comptes d'un jeu précédent (les prénoms et les fiches suivent par cascade)
    private function purge(): void
    {
        User::where('mail', 'like', '%@'.self::MAIL_DOMAIN)->get()->each(function (User $user): void {
            $user->syncRoles([]);
            $user->tokens()->delete();
            $user->delete();
        });
    }

    private function report(): void
    {
        $this->command?->newLine();
        $this->command?->info(
            'Jeu '.class_basename($this).' : '.count($this->rows).' comptes (mot de passe : password).',
        );
        $this->command?->table(
            ['id', 'compte', 'nom', 'prénoms', 'rôle', 'statut', 'voix',],
            $this->rows,
        );
    }
}
