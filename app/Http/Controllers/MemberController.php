<?php

namespace App\Http\Controllers;

use App\Actions\Member\AssignVoiceAction;
use App\Actions\Member\CreateMemberAction;
use App\Actions\Member\ExportMembersAction;
use App\Actions\Member\ListMembersAction;
use App\Actions\Member\UpdateMemberProfileAction;
use App\Actions\User\ValidateMemberAction;
use App\Http\Requests\Member\AssignVoiceRequest;
use App\Http\Requests\Member\CreateMemberRequest;
use App\Http\Requests\Member\ExportMembersRequest;
use App\Http\Requests\Member\ListMembersRequest;
use App\Http\Requests\Member\UpdateMemberProfileRequest;
use App\Http\Resources\MemberListItemResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    public function export(ExportMembersRequest $request, ExportMembersAction $action): Response
    {
        return $action($request->toDTO());
    }

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

    public function store(CreateMemberRequest $request, CreateMemberAction $action): JsonResponse
    {
        return UserResource::make($action($request->toDTO()))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    // Identifiant brut (pas de route model binding) : le refus 403 de la route passe avant tout 404
    public function show(int $member, UserRepository $users): UserResource
    {
        return UserResource::make($users->findRecordById($member));
    }

    public function update(UpdateMemberProfileRequest $request, int $member, UpdateMemberProfileAction $action): UserResource
    {
        return UserResource::make($action($member, $request->toDTO()));
    }

    // Identifiant brut, comme update() : le refus 403 de la route passe avant tout 404
    public function assignVoice(AssignVoiceRequest $request, AssignVoiceAction $action): UserResource
    {
        return UserResource::make($action($request->toDTO()));
    }

    public function validateAccount(User $member, ValidateMemberAction $action): UserResource
    {
        return UserResource::make($action($member));
    }
}
