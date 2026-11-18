<?php

namespace App\Enums;

// Précision d'une date de la fiche de recensement : la saisie « 2008 » ou « 05/1998 » est permise quand le jour est inconnu
enum DatePrecision: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';
}
