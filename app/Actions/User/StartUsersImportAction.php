<?php

namespace App\Actions\User;

use App\DTOs\User\ImportUsersDTO;
use App\DTOs\User\StartUsersImportDTO;
use App\Jobs\ImportUsersJob;
use App\Models\UserImport;
use App\Repositories\UserImportRepository;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

// Met un import en file d'attente. Le fichier est contrôlé tout de suite (mêmes refus 422 que l'aperçu), puis gardé sur
// le disque privé le temps du traitement. Le front suit ensuite l'avancement avec `GET /api/users/import/{id}`.
readonly class StartUsersImportAction
{
    public function __construct(
        private AnalyzeUsersImportAction $analyze,
        private UserImportRepository $imports,
    ) {}

    public function __invoke(StartUsersImportDTO $dto): UserImport
    {
        // Fichier illisible, vide, colonnes manquantes... : refusé ici, avant de stocker quoi que ce soit ou de lancer le Job
        $analysis = ($this->analyze)(new ImportUsersDTO($dto->path));

        $path = Storage::disk(UserImport::FILE_DISK)->putFileAs(
            UserImport::FILE_DIRECTORY,
            new File($dto->path),
            Str::random(40).'.xlsx',
        ) ?: throw new RuntimeException("Le fichier d'import n'a pas pu être enregistré.");

        $import = $this->imports->create($dto->userId, $path, $analysis->steps(), $dto->fileName);

        ImportUsersJob::dispatch($import->id);

        // Avec une file synchrone (tests), le traitement est déjà fini : on renvoie l'état à jour
        return $import->refresh();
    }
}
