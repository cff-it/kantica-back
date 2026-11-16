<?php

namespace App\Http\Controllers;

use App\Actions\Event\CheckInMemberAction;
use App\Actions\Event\CreateEventAction;
use App\Actions\Event\SubscribeToEventAction;
use App\Http\Requests\Event\CheckInRequest;
use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\SubscribeToEventRequest;
use App\Http\Resources\EventResource;
use App\Repositories\EventRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function index(EventRepository $events): AnonymousResourceCollection
    {
        return EventResource::collection($events->all());
    }

    public function show(int $event, EventRepository $events): EventResource
    {
        return EventResource::make($events->findById($event));
    }

    public function store(CreateEventRequest $request, CreateEventAction $action): JsonResponse
    {
        $event = $action($request->toDTO());

        return EventResource::make($event)->response()->setStatusCode(201);
    }

    public function subscribe(SubscribeToEventRequest $request, int $event, SubscribeToEventAction $action): JsonResponse
    {
        $subscription = $action($request->toDTO());

        return response()->json($subscription, 201);
    }

    public function checkIn(CheckInRequest $request, int $event, CheckInMemberAction $action): JsonResponse
    {
        $check = $action($request->toDTO());

        return response()->json($check, 201);
    }
}
