<?php

namespace App\Actions\Event;

use App\Models\Event;
use App\Support\ChoirTime;
use Illuminate\Validation\ValidationException;

/**
 * Règle du jour J (KAN-12) : les présences se saisissent à partir du jour de l'événement, pas avant. Un événement dont
 * la date de début est après la fin de la journée en cours (fuseau de la chorale) est refusé en lecture comme en saisie.
 */
readonly class EnsureAttendancesOpenAction
{
    public function __invoke(Event $event): void
    {
        if ($event->date_start->greaterThan(ChoirTime::endOfToday())) {
            throw ValidationException::withMessages([
                'event' => ['Les présences se saisissent à partir du jour de l\'événement.',],
            ]);
        }
    }
}
