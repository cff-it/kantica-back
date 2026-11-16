<?php

namespace App\Http\Controllers;

use App\Actions\User\AnalyzeUsersImportAction;
use App\Actions\User\DownloadUsersImportTemplateAction;
use App\Actions\User\StartUsersImportAction;
use App\Http\Requests\User\ImportUsersRequest;
use App\Http\Resources\UserImportResource;
use App\Http\Resources\UsersImportPreviewResource;
use App\Models\UserImport;
use Illuminate\Http\JsonResponse;
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

    // Avancement (pourcentage), puis rapport final
    public function show(UserImport $userImport): UserImportResource
    {
        return UserImportResource::make($userImport);
    }
}
