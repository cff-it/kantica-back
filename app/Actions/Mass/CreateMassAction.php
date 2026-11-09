<?php

namespace App\Actions\Mass;

use App\DTOs\Mass\CreateMassDTO;
use App\Models\Mass;
use App\Repositories\MassRepository;

readonly class CreateMassAction
{
    public function __construct(private MassRepository $masses) {}

    public function __invoke(CreateMassDTO $dto): Mass
    {
        return $this->masses->create([
            'date'            => $dto->date,
            'liturgical_name' => $dto->liturgicalName,
            'prayer_cycle'    => $dto->prayerCycle,
            'notes'           => $dto->notes,
            'is_public'       => $dto->isPublic,
        ]);
    }
}
