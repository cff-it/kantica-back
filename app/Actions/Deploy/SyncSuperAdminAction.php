<?php

namespace App\Actions\Deploy;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

readonly class SyncSuperAdminAction
{
    public function __construct(
        private UserRepository $users,
    ) {}

    // Idempotent : crée le compte admin originel tant qu'aucun admin n'existe, sans jamais modifier un compte existant
    public function __invoke(): int
    {
        if ($this->users->existsWithRole(RoleName::Admin->value)) {
            return 0;
        }

        $mail = config('kantica.super_admin.mail');

        if (blank($mail)) {
            throw new RuntimeException('Aucun admin n\'existe : définir SUPER_ADMIN_MAIL et SUPER_ADMIN_PASSWORD.');
        }

        // Un compte non admin utilise déjà cet email : on ne l'élève pas automatiquement
        if ($this->users->findByMail($mail) !== null) {
            throw new RuntimeException("Le compte {$mail} existe déjà sans le rôle admin : choisir un autre SUPER_ADMIN_MAIL.");
        }

        $password = config('kantica.super_admin.password');

        Validator::make(
            ['mail' => $mail, 'password' => $password,],
            [
                'mail'     => ['required', 'email', 'max:255',],
                'password' => ['required', 'string', Password::defaults(),],
            ],
            [
                'mail.email'                => 'SUPER_ADMIN_MAIL n\'est pas une adresse email valide.',
                'password.required'         => 'SUPER_ADMIN_PASSWORD est requis pour créer le compte admin originel.',
                'password.min'              => 'SUPER_ADMIN_PASSWORD doit contenir au moins 8 caractères.',
                'password.password.mixed'   => 'SUPER_ADMIN_PASSWORD doit contenir au moins une majuscule et une minuscule.',
                'password.password.letters' => 'SUPER_ADMIN_PASSWORD doit contenir au moins une lettre.',
                'password.password.numbers' => 'SUPER_ADMIN_PASSWORD doit contenir au moins un chiffre.',
                'password.password.symbols' => 'SUPER_ADMIN_PASSWORD doit contenir au moins un caractère spécial.',
            ],
        )->validate();

        DB::transaction(function () use ($mail, $password): void {
            $user = $this->users->create([
                'mail'     => $mail,
                'password' => $password,
                'status'   => UserStatus::Active,
            ]);

            $user->markEmailAsVerified();
            $user->assignRole(RoleName::Admin->value);
        });

        return 1;
    }
}
