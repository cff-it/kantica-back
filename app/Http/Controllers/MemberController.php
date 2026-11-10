<?php

namespace App\Http\Controllers;

use App\Actions\Member\ListMembersAction;
use App\Actions\Member\UpdateMemberProfileAction;
use App\Actions\User\ValidateMemberAction;
use App\Http\Requests\Member\ListMembersRequest;
use App\Http\Requests\Member\UpdateMemberProfileRequest;
use App\Http\Resources\MemberListItemResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberController extends Controller
{
    public function index(ListMembersRequest $request, ListMembersAction $action): AnonymousResourceCollection
    {
        $list = $action($request->toDTO());

        // `meta.total` (pagination) suit la recherche et le filtre ; `registered_count` et `voice_counts` non
        return MemberListItemResource::collection($list->members)->additional([
            'meta' => [
                'registered_count' => $list->registeredCount,
                'voice_counts'     => $list->voiceCounts,
            ],
        ]);
    }

    // Identifiant brut (pas de route model binding) : le refus 403 de la route passe avant tout 404
    public function show(int $member, UserRepository $users): UserResource
    {
        return UserResource::make($users->findById($member));
    }

    public function update(UpdateMemberProfileRequest $request, int $member, UpdateMemberProfileAction $action): UserResource
    {
        return UserResource::make($action($member, $request->toDTO()));
    }

    public function validateAccount(User $member, ValidateMemberAction $action): UserResource
    {
        return UserResource::make($action($member));
    }
}
