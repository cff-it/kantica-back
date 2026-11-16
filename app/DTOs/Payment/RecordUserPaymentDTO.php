<?php

namespace App\DTOs\Payment;

class RecordUserPaymentDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly int $eventPaymentId,
        public readonly int $amountDue,
        public readonly int $paidAmount = 0,
        public readonly ?string $comment = null,
    ) {}
}
