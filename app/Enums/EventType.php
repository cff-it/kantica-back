<?php

namespace App\Enums;

// Type d'un événement (KAN-12, formulaire « Nouvel événement ») : filtre et pastille de couleur de l'écran des présences
enum EventType: string
{
    case Repetition = 'repetition';
    case Mass = 'messe';
    case Concert = 'concert';
    case Other = 'autre';
}
