<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compte administrateur originel
    |--------------------------------------------------------------------------
    |
    | Créé par `php artisan app:deploy` tant qu'aucun admin n'existe. Les variables
    | ne sont nécessaires qu'au premier déploiement : un compte existant n'est
    | jamais modifié (son mot de passe se change ensuite depuis l'application).
    |
    */

    'super_admin' => [
        'mail'     => env('SUPER_ADMIN_MAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fuseau de la chorale
    |--------------------------------------------------------------------------
    |
    | Fuseau dans lequel se lisent « le jour de l'événement » (les présences se saisissent à partir de ce jour, KAN-12)
    | et l'heure saisie dans « Nouvel événement » quand elle n'a pas de décalage. `app.timezone` reste en UTC : les dates
    | déjà enregistrées sont en UTC, le changer les décalerait toutes.
    |
    */

    'timezone' => env('KANTICA_TIMEZONE', 'Indian/Antananarivo'),

];
