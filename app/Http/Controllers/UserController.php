<?php

namespace App\Http\Controllers;

use App\Actions\User\AssignRoleAction;
use App\Actions\User\CreateUserAction;
use App\Actions\User\ListUsersWithRolesAction;
use App\Http\Requests\User\AssignRoleRequest;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserRoleResource;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    // « Gestion des rôles » (US-25) : comptes, rôle de chacun et décompte par rôle (« 1 admin · 1 secrétaire · 5 membres »)
    public function index(ListUsersRequest $request, ListUsersWithRolesAction $action): AnonymousResourceCollection
    {
        $list = $action($request->toDTO());

        return UserRoleResource::collection($list->users)->additional([
            'meta' => ['role_counts' => $list->roleCounts,],
        ]);
    }

    public function show(int $user, UserRepository $users): UserResource
    {
        return UserResource::make($users->findById($user));
    }

    public function store(CreateUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action($request->toDTO());

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function assignRole(AssignRoleRequest $request, AssignRoleAction $action): UserRoleResource
    {
        return UserRoleResource::make($action($request->toDTO()));
    }
}
