<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * @unauthenticated
     */
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $registered = $action($request->toDTO());

        return response()->json(
            [
                'token' => $registered->token,
                'user'  => UserResource::make($registered->user),
            ],
            201,
        );
    }

    /**
     * @unauthenticated
     */
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $token = $action($request->toDTO());

        return response()->json(['token' => $token]);
    }

    public function me(Request $request, UserRepository $users): UserResource
    {
        return UserResource::make($users->loadCurrentUser($request->user()));
    }

    public function logout(Request $request, LogoutAction $action): JsonResponse
    {
        $action($request->user());

        return response()->json(null, 204);
    }
}
