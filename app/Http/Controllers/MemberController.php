<?php

namespace App\Http\Controllers;

use App\Actions\User\ValidateMemberAction;
use App\Http\Resources\UserResource;
use App\Models\User;

class MemberController extends Controller
{
    public function validateAccount(User $member, ValidateMemberAction $action): UserResource
    {
        return UserResource::make($action($member));
    }
}
