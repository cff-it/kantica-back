<?php

namespace App\Console\Commands;

use App\Actions\Deploy\SyncRolesAction;
use App\Actions\Deploy\SyncSuperAdminAction;
use App\Actions\Deploy\SyncVoicesAction;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeployCommand extends Command
{
    protected $signature = 'app:deploy
                            {--skip-migrate : Ne pas lancer les migrations}';

    protected $description = 'Tâches à exécuter à chaque mise en production (migrations et données de référence)';

    /**
     * Étapes exécutées dans l'ordre, après les migrations. Chaque Action doit être idempotente
     * et retourner le nombre d'éléments créés ou mis à jour. Pour ajouter une tâche : ajouter une ligne ici.
     *
     * @var array<string, class-string>
     */
    private const array STEPS = [
        'Rôles'                   => SyncRolesAction::class,
        'Admin originel'          => SyncSuperAdminAction::class,
        'Groupes de voix et voix' => SyncVoicesAction::class,
    ];

    public function handle(): int
    {
        if (!$this->option('skip-migrate') && $this->call('migrate', ['--force' => true,]) !== self::SUCCESS) {
            $this->components->error('Échec des migrations : déploiement interrompu.');

            return self::FAILURE;
        }

        $this->components->info('Données de référence');

        foreach (self::STEPS as $label => $action) {
            try {
                $created = app($action)();
            } catch (RuntimeException|ValidationException $exception) {
                $message = $exception instanceof ValidationException
                    ? implode(' ', $exception->validator->errors()->all())
                    : $exception->getMessage();

                $this->components->error("{$label} : {$message} Déploiement interrompu.");

                return self::FAILURE;
            }

            $this->components->twoColumnDetail($label, "<fg=green>{$created} créé(s) ou mis à jour</>");
        }

        $this->newLine();
        $this->components->info('Déploiement terminé.');

        return self::SUCCESS;
    }
}
