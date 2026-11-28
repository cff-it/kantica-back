<?php

namespace App\Support;

use Carbon\Carbon;

// Heure de la chorale (KAN-12) : les dates sont stockées dans le fuseau de l'application (UTC), mais « le jour de
// l'événement » se lit dans le fuseau de la chorale (`kantica.timezone`, Indian/Antananarivo)
final class ChoirTime
{
    // Fin de la journée en cours dans le fuseau de la chorale, exprimée dans le fuseau de l'application : directement
    // comparable aux dates des événements en base
    public static function endOfToday(): Carbon
    {
        return Carbon::now(config('kantica.timezone'))
            ->endOfDay()
            ->setTimezone(config('app.timezone'));
    }

    // Date saisie dans un formulaire : sans décalage (« 2026-12-05 17:00 »), elle se lit dans le fuseau de la chorale ;
    // avec un décalage (« …T17:00:00+03:00 », « …Z »), il est respecté. Le résultat est dans le fuseau de l'application
    public static function parse(string $value): Carbon
    {
        return Carbon::parse($value, config('kantica.timezone'))->setTimezone(config('app.timezone'));
    }
}
