<?php

namespace App\Http\Controllers;

use App\Actions\Member\UpdateOwnProfileAction;
use App\Http\Requests\Member\UpdateOwnProfileRequest;
use App\Http\Resources\MemberProfileResource;
use App\Repositories\MemberProfileRepository;
use Illuminate\Http\Request;

class MemberProfileController extends Controller
{
    public function show(Request $request, MemberProfileRepository $profiles): MemberProfileResource
    {
        return MemberProfileResource::make($profiles->load($request->user()));
    }

    public function update(UpdateOwnProfileRequest $request, UpdateOwnProfileAction $action): MemberProfileResource
    {
        return MemberProfileResource::make($action($request->user(), $request->toDTO()));
    }
}
