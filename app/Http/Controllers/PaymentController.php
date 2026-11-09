<?php

namespace App\Http\Controllers;

use App\Actions\Payment\RecordUserPaymentAction;
use App\Http\Requests\Payment\RecordUserPaymentRequest;
use App\Http\Resources\EventPaymentResource;
use App\Http\Resources\UserPaymentResource;
use App\Repositories\PaymentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(int $event, PaymentRepository $payments): AnonymousResourceCollection
    {
        return EventPaymentResource::collection($payments->paymentsForEvent($event));
    }

    public function userPayments(int $event, PaymentRepository $payments): AnonymousResourceCollection
    {
        return UserPaymentResource::collection($payments->userPaymentsForEvent($event));
    }

    public function record(RecordUserPaymentRequest $request, int $event, RecordUserPaymentAction $action): JsonResponse
    {
        $payment = $action($request->toDTO());

        return UserPaymentResource::make($payment)->response()->setStatusCode(201);
    }
}
