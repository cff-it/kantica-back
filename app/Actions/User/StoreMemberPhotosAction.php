<?php

namespace App\Actions\User;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

// Télécharge les photos d'identité (liens Google Drive du formulaire) dans le dossier de l'application :
// `storage/app/public/members/photos`. Ces fichiers sont ceux qu'on sauvegardera ensuite dans un Drive.
// Dix photos à la fois, et pas plus de 40 secondes en tout : la requête d'import doit finir avant le délai de nginx (60 s).
readonly class StoreMemberPhotosAction
{
    private const string DOWNLOAD_URL = 'https://drive.usercontent.google.com/download';

    private const string DISK = 'public';

    private const string DIRECTORY = 'members/photos';

    private const int MAX_BYTES = 5242880;

    private const int CHUNK_SIZE = 10;

    private const int TIMEOUT_SECONDS = 10;

    private const int BUDGET_SECONDS = 40;

    private const array EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];

    public function __construct(
        private ExtractDriveFileIdAction $extractFileId,
    ) {}

    /**
     * @param  array<int, string>  $links  lien Drive de la photo, indexé par l'identifiant de l'utilisateur
     * @return array<int, array{url: string|null, warning: string|null}> URL de la photo enregistrée, ou raison de l'échec
     */
    public function __invoke(array $links): array
    {
        $startedAt = microtime(true);
        $results = [];

        foreach (array_chunk($links, self::CHUNK_SIZE, true) as $chunk) {
            if (microtime(true) - $startedAt > self::BUDGET_SECONDS) {
                foreach ($chunk as $userId => $link) {
                    $results[$userId] = $this->failure("délai de l'import dépassé");
                }

                continue;
            }

            $responses = Http::pool(function (Pool $pool) use ($chunk): void {
                foreach ($chunk as $userId => $link) {
                    $pool->as((string) $userId)
                        ->timeout(self::TIMEOUT_SECONDS)
                        ->withOptions(['on_headers' => $this->refuseHeavyFile(...),])
                        ->get(self::DOWNLOAD_URL, [
                            'id'      => ($this->extractFileId)($link),
                            'export'  => 'download',
                            'confirm' => 't',
                        ]);
                }
            });

            foreach ($chunk as $userId => $link) {
                $results[$userId] = $this->store($responses[(string) $userId] ?? null);
            }
        }

        return $results;
    }

    // Arrête le téléchargement dès les en-têtes : un gros fichier ne doit pas remplir la mémoire
    private function refuseHeavyFile(ResponseInterface $response): void
    {
        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
            throw new RuntimeException('Fichier trop lourd.');
        }
    }

    /**
     * @return array{url: string|null, warning: string|null}
     */
    private function store(mixed $response): array
    {
        if (!$response instanceof Response || !$response->successful()) {
            return $this->failure('lien privé, introuvable ou fichier de plus de 5 Mo');
        }

        // Un fichier privé renvoie la page de connexion de Google, pas une image
        if (str_starts_with((string) $response->header('Content-Type'), 'text/html')) {
            return $this->failure('lien privé : partagez la photo avec « Toute personne disposant du lien »');
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            return $this->failure('fichier de plus de 5 Mo');
        }

        $info = @getimagesizefromstring($body);
        $extension = $info === false ? null : (self::EXTENSIONS[$info[2]] ?? null);

        if ($extension === null) {
            return $this->failure('format non pris en charge (JPEG, PNG, WebP ou GIF attendus)');
        }

        // Nom aléatoire : les photos sont servies par leur adresse, qui ne doit pas se deviner
        $path = self::DIRECTORY.'/'.Str::random(40).'.'.$extension;
        Storage::disk(self::DISK)->put($path, $body);

        return ['url' => Storage::disk(self::DISK)->url($path), 'warning' => null,];
    }

    /**
     * @return array{url: null, warning: string}
     */
    private function failure(string $reason): array
    {
        return ['url' => null, 'warning' => 'Photo non téléchargée : '.$reason.'.',];
    }
}
