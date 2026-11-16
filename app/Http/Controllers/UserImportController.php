<?php

namespace App\Http\Controllers;

use App\Actions\User\AnalyzeUsersImportAction;
use App\Actions\User\DownloadUsersImportTemplateAction;
use App\Actions\User\ImportUsersAction;
use App\Http\Requests\User\ImportUsersRequest;
use App\Http\Resources\UsersImportPreviewResource;
use App\Http\Resources\UsersImportReportResource;
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

    public function import(ImportUsersRequest $request, ImportUsersAction $action): UsersImportReportResource
    {
        return UsersImportReportResource::make($action($request->toDTO()));
    }
}
