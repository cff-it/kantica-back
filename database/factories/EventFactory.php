<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'       => 'Répétition',
            'type'       => EventType::Repetition,
            'date_start' => now()->addDays(7)->setTime(17, 0),
            'location'   => 'Salle paroissiale',
        ];
    }

    public function ofType(EventType $type, string $name): static
    {
        return $this->state(['type' => $type, 'name' => $name,]);
    }

    public function startingAt(Carbon|string $dateStart): static
    {
        return $this->state(['date_start' => $dateStart,]);
    }

    // Présences saisies (« Déjà saisis ») : le champ, et non le nombre de présences, fait foi
    public function recorded(Carbon|string|null $at = null): static
    {
        return $this->state(['attendances_recorded_at' => $at ?? now(),]);
    }
}
