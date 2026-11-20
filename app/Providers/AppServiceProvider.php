<?php

namespace App\Providers;

use App\Models\UserProfile;
use App\Policies\MemberProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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

        // KAN-33 : fiches de recensement des autres membres (nom de policy non devinable depuis le modèle)
        Gate::policy(UserProfile::class, MemberProfilePolicy::class);

        // Hors production, tous les mails (Mailable et Notification) sont redirigés vers une seule boîte de test.
        // Sans effet si MAIL_ALWAYS_TO est vide.
        $alwaysTo = config('mail.always_to');

        if (filled($alwaysTo) && !$this->app->isProduction()) {
            Mail::alwaysTo($alwaysTo);
        }
    }
}
