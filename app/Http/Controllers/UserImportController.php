<?php

namespace App\Http\Controllers;

use App\Actions\User\AnalyzeUsersImportAction;
use App\Actions\User\DownloadUsersImportTemplateAction;
use App\Actions\User\ListUsersImportsAction;
use App\Actions\User\StartUsersImportAction;
use App\Http\Requests\User\ImportUsersRequest;
use App\Http\Requests\User\ListUsersImportsRequest;
use App\Http\Resources\UserImportListItemResource;
use App\Http\Resources\UserImportResource;
use App\Http\Resources\UsersImportPreviewResource;
use App\Models\UserImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportController extends Controller
{
    public function template(DownloadUsersImportTemplateAction $action): StreamedResponse
    {
        return $action();
    }

    // Vérification du fichier : aucune écriture en base
    public function preview(ImportUsersRequest $request, AnalyzeUsersImportAction $action): UsersImportPreviewResource
    {
        return UsersImportPreviewResource::make($action($request->toDTO()));
    }

    // Lance l'import en tâche de fond : 202 avec l'identifiant à interroger pour suivre l'avancement
    public function import(ImportUsersRequest $request, StartUsersImportAction $action): JsonResponse
    {
        return UserImportResource::make($action($request->toStartDTO()))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    // Historique : l'admin voit tous les imports, la secrétaire les siens
    public function index(ListUsersImportsRequest $request, ListUsersImportsAction $action): AnonymousResourceCollection
    {
        return UserImportListItemResource::collection($action($request->toDTO()));
    }

    // Détail d'un import : auteur, dates, avancement (pourcentage), puis rapport complet
    public function show(UserImport $userImport): UserImportResource
    {
        return UserImportResource::make($userImport);
    }
}
