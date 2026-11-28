<?php

namespace App\Http\Controllers;

use App\Actions\Song\AttachSongToMassAction;
use App\Actions\Song\CreateSongAction;
use App\Actions\Song\DeleteSongAction;
use App\Actions\Song\ListSongsAction;
use App\Actions\Song\UpdateSongAction;
use App\Http\Requests\Song\AttachSongToMassRequest;
use App\Http\Requests\Song\CreateSongRequest;
use App\Http\Requests\Song\DeleteSongRequest;
use App\Http\Requests\Song\ListSongsRequest;
use App\Http\Requests\Song\UpdateSongRequest;
use App\Http\Resources\MassPartResource;
use App\Http\Resources\SongListItemResource;
use App\Http\Resources\SongResource;
use App\Repositories\SongRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SongController extends Controller
{
    // Répertoire (US-04) : recherche par titre ou auteur, 20 cantiques par page, triés par titre
    public function index(ListSongsRequest $request, ListSongsAction $action): AnonymousResourceCollection
    {
        $list = $action($request->toDTO());

        // `meta.total` (pagination) suit la recherche ; `songs_count` (« 13 cantiques ») porte sur tout le répertoire
        return SongListItemResource::collection($list->songs)->additional([
            'meta' => ['songs_count' => $list->songsCount,],
        ]);
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

    // Réservée à l'admin : la route applique SongPolicy::delete avant tout 404, DeleteSongRequest le vérifie aussi
    public function destroy(DeleteSongRequest $request, int $song, DeleteSongAction $action): JsonResponse
    {
        $action($song);

        return response()->json(null, 204);
    }

    public function attach(AttachSongToMassRequest $request, int $song, AttachSongToMassAction $action): MassPartResource
    {
        return MassPartResource::make($action($request->toDTO()));
    }
}
