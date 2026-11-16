<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // KAN-28 : 8 caractères minimum, majuscule, minuscule, chiffre et caractère spécial
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());
    }
}
