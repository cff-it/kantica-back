<?php

namespace App\Enums;

enum UserImportStatus: string
{
    // En file d'attente, pas encore pris en charge
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    // Arrêté par une erreur : les membres déjà créés sont conservés
    case Failed = 'failed';
}
