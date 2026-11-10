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

];
