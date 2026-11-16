<?php

namespace App\Http\Controllers;

use App\Actions\Song\AttachSongToMassAction;
use App\Actions\Song\CreateSongAction;
use App\Actions\Song\UpdateSongAction;
use App\Http\Requests\Song\AttachSongToMassRequest;
use App\Http\Requests\Song\CreateSongRequest;
use App\Http\Requests\Song\UpdateSongRequest;
use App\Http\Resources\MassPartResource;
use App\Http\Resources\SongResource;
use App\Repositories\SongRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SongController extends Controller
{
    public function index(SongRepository $songs): AnonymousResourceCollection
    {
        return SongResource::collection($songs->all());
    }

    public function show(int $song, SongRepository $songs): SongResource
    {
        return SongResource::make($songs->findById($song));
    }

    public function store(CreateSongRequest $request, CreateSongAction $action): JsonResponse
    {
        $song = $action($request->toDTO());

        return SongResource::make($song)->response()->setStatusCode(201);
    }

    public function update(UpdateSongRequest $request, int $song, UpdateSongAction $action): SongResource
    {
        return SongResource::make($action($request->toDTO()));
    }

    public function destroy(int $song, SongRepository $songs): JsonResponse
    {
        $songs->delete($songs->findById($song));

        return response()->json(null, 204);
    }

    public function attach(AttachSongToMassRequest $request, int $song, AttachSongToMassAction $action): MassPartResource
    {
        return MassPartResource::make($action($request->toDTO()));
    }
}
