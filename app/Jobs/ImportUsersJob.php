<?php

namespace App\Jobs;

use App\Actions\User\FailUsersImportAction;
use App\Actions\User\RunUsersImportAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

// Import de membres (KAN-31) exécuté en tâche de fond : le front suit l'avancement avec `GET /api/users/import/{id}`.
// Demande un worker (`php artisan queue:work`) : sans lui l'import reste « pending ».
class ImportUsersJob implements ShouldQueue
{
    use Queueable;

    // Un seul essai : un import à moitié fait n'est pas rejoué, les membres déjà créés seraient ignorés au second passage
    public int $tries = 1;

    // Doit rester inférieur à `retry_after` de la file (config/queue.php), sinon un second worker reprendrait l'import en cours
    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $userImportId,
    ) {}

    public function handle(RunUsersImportAction $action): void
    {
        $action($this->userImportId);
    }

    public function failed(Throwable $exception): void
    {
        app(FailUsersImportAction::class)($this->userImportId);
    }
}
