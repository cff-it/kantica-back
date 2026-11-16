<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class ExportPostmanCollection extends Command
{
    protected $signature = 'postman:export
                            {--name= : Nom de la collection (défaut: nom du projet)}
                            {--output= : Chemin du fichier de sortie (défaut: postman_collection.json)}
                            {--base-url= : Valeur de la variable {{base_url}} (défaut: APP_URL)}
                            {--only-api : N\'exporter que les routes /api/*}';

    protected $description = 'Génère une collection Postman depuis les routes Laravel';

    // Identifiant fixe : au réimport, Postman reconnaît la collection et propose de la remplacer au lieu de la dupliquer
    private const string COLLECTION_ID = '8b779442-5b42-4dc5-88e9-826867b5286c';

    public function handle(): int
    {
        $collectionName = $this->option('name') ?? config('app.name', 'Laravel');
        $outputPath = $this->option('output') ?? base_path('postman_collection.json');
        $baseUrl = rtrim($this->option('base-url') ?? config('app.url', 'http://localhost'), '/');
        $onlyApi = $this->option('only-api');

        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => !$onlyApi || Str::startsWith($route->uri(), 'api/'))
            ->filter(fn (Route $route) => !Str::startsWith($route->uri(), '_'))
            ->values();

        $collection = $this->buildCollection($collectionName, $baseUrl, $routes);

        file_put_contents(
            $outputPath,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        $this->info("Collection Postman exportée : {$outputPath}");
        $this->line("  Nombre de routes : {$routes->count()}");

        return self::SUCCESS;
    }

    private function buildCollection(string $name, string $baseUrl, Collection $routes): array
    {
        return [
            'info' => [
                '_postman_id' => self::COLLECTION_ID,
                'name' => $name,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                ['key' => 'base_url', 'value' => $baseUrl, 'type' => 'string',],
            ],
            'item' => $this->buildFolders($routes),
        ];
    }

    private function buildFolders(Collection $routes): array
    {
        $folders = [];

        foreach ($routes as $route) {
            $methods = array_filter($route->methods(), fn (string $method) => $method !== 'HEAD');
            $folderName = $this->resolveFolderName($route->uri());

            foreach ($methods as $method) {
                $folders[$folderName][] = $this->buildRequest($method, $route);
            }
        }

        $items = [];

        foreach ($folders as $folderName => $requests) {
            $items[] = [
                'name' => $folderName,
                'item' => $requests,
            ];
        }

        return $items;
    }

    private function resolveFolderName(string $uri): string
    {
        $segments = explode('/', ltrim($uri, '/'));

        // Routes API : grouper par la ressource (2e segment après "api/")
        if ($segments[0] === 'api' && isset($segments[1])) {
            return ucfirst($segments[1]);
        }

        // Routes système regroupées dans un dossier dédié
        if (in_array($segments[0], ['storage', 'up'])) {
            return 'Système';
        }

        return $segments[0] !== '' ? ucfirst($segments[0]) : 'Root';
    }

    private function buildRequest(string $method, Route $route): array
    {
        $uri = ltrim($route->uri(), '/');
        $method = strtoupper($method);

        // Transformer les paramètres {param} en :param pour Postman
        $pathSegments = array_map(
            fn (string $segment) => preg_match('/^\{(\w+)\??}$/', $segment, $match) ? ':'.$match[1] : $segment,
            array_values(array_filter(explode('/', $uri), fn (string $segment) => $segment !== '')),
        );

        preg_match_all('/\{(\w+)\??}/', $uri, $matches);

        $variables = array_map(
            fn (string $param) => ['key' => $param, 'value' => '', 'description' => '',],
            $matches[1],
        );

        $request = [
            'method' => $method,
            'header' => [
                ['key' => 'Accept', 'value' => 'application/json',],
                ['key' => 'Content-Type', 'value' => 'application/json',],
            ],
            'url' => [
                'raw' => '{{base_url}}/'.implode('/', $pathSegments),
                'host' => ['{{base_url}}',],
                'path' => $pathSegments,
                'variable' => $variables,
            ],
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $request['body'] = [
                'mode' => 'raw',
                'raw' => '{}',
                'options' => ['raw' => ['language' => 'json',],],
            ];
        }

        $actionName = $route->getActionName();

        if ($actionName !== 'Closure') {
            $request['description'] = $actionName;
        }

        return [
            'name' => $method.' /'.$uri,
            'request' => $request,
            'response' => [],
        ];
    }
}
