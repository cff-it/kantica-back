<?php

namespace App\Http\Controllers;

use App\Actions\Member\ExportMembersAction;
use App\Actions\User\ValidateMemberAction;
use App\Http\Requests\Member\ExportMembersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    public function export(ExportMembersRequest $request, ExportMembersAction $action): Response
    {
        return $action($request->toDTO());
    }

    public function validateAccount(User $member, ValidateMemberAction $action): UserResource
    {
        return UserResource::make($action($member));
    }
}
