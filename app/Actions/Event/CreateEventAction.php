<?php

namespace App\Actions\Event;

use App\DTOs\Event\CreateEventDTO;
use App\Models\Event;
use App\Repositories\EventRepository;

readonly class CreateEventAction
{
    public function __construct(private EventRepository $events) {}

    public function __invoke(CreateEventDTO $dto): Event
    {
        return $this->events->create([
            'name'           => $dto->name,
            'date_start'     => $dto->dateStart,
            'date_end'       => $dto->dateEnd,
            'need_payment'   => $dto->needPayment,
            'payment_amount' => $dto->paymentAmount,
        ]);
    }
}
