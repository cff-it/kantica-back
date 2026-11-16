<?php

namespace App\Http\Controllers;

use App\Actions\Mass\AssignSongToMassPartAction;
use App\Actions\Mass\CreateMassAction;
use App\Actions\Mass\GeneratePptxAction;
use App\Http\Requests\Mass\AssignSongToMassPartRequest;
use App\Http\Requests\Mass\CreateMassRequest;
use App\Http\Resources\MassResource;
use App\Repositories\MassRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MassController extends Controller
{
    public function index(MassRepository $masses): AnonymousResourceCollection
    {
        return MassResource::collection($masses->all());
    }

    public function show(int $mass, MassRepository $masses): MassResource
    {
        return MassResource::make($masses->findById($mass));
    }

    public function store(CreateMassRequest $request, CreateMassAction $action): JsonResponse
    {
        $mass = $action($request->toDTO());

        return MassResource::make($mass)->response()->setStatusCode(201);
    }

    public function assignSong(AssignSongToMassPartRequest $request, int $mass, int $part, AssignSongToMassPartAction $action): JsonResponse
    {
        $massSong = $action($request->toDTO());

        return response()->json($massSong->load('song'), 201);
    }

    public function generatePptx(int $mass, GeneratePptxAction $action): BinaryFileResponse
    {
        $path = $action($mass);

        return response()->download($path);
    }
}
