<?php

namespace App\Actions\Payment;

use App\DTOs\Payment\RecordUserPaymentDTO;
use App\Models\UserPayment;
use App\Repositories\UserRepository;

readonly class RecordUserPaymentAction
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(RecordUserPaymentDTO $dto): UserPayment
    {
        $this->users->findById($dto->userId);

        return UserPayment::create([
            'user_id'          => $dto->userId,
            'event_payment_id' => $dto->eventPaymentId,
            'amount_due'       => $dto->amountDue,
            'paid_amount'      => $dto->paidAmount,
            'comment'          => $dto->comment,
            'is_cancelled'     => false,
            'is_done'          => $dto->paidAmount >= $dto->amountDue,
        ]);
    }
}
