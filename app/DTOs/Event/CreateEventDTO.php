<?php

namespace App\DTOs\Event;

use App\Enums\EventType;
use Carbon\Carbon;

class CreateEventDTO
{
    public function __construct(
        public readonly string $name,
        public readonly EventType $type,
        public readonly Carbon $dateStart,
        public readonly ?string $location = null,
        public readonly bool $needPayment = false,
        public readonly ?Carbon $dateEnd = null,
        public readonly ?int $paymentAmount = null,
    ) {}
}
