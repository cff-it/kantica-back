<?php

namespace App\DTOs\Event;

use Carbon\Carbon;

class CreateEventDTO
{
    public function __construct(
        public readonly string $name,
        public readonly Carbon $dateStart,
        public readonly bool $needPayment = false,
        public readonly ?Carbon $dateEnd = null,
        public readonly ?int $paymentAmount = null,
    ) {}
}
