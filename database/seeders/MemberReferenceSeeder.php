<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Jeu de référence : scénarios L (liste), S (recherche), F (filtre), V (fiche), M (modification) et A (accès).
 * Les noms des comptes sont choisis pour que le tri et chaque recherche du plan donnent le résultat annoncé.
 */
class MemberReferenceSeeder extends MemberScenarioSeeder
{
    protected function members(): void
    {
        // Comptes qui agissent dans les scénarios. La secrétaire et l'admin sont aussi des choristes de la liste
        $this->member('secretaire', 'Ratsimba', 'Sarah', 'A', role: RoleName::Secretary, phone: '034 10 000 01');
        $this->member('admin', 'Randria', 'Tojo', 'B', role: RoleName::Admin, phone: '034 10 000 02');
        $this->member('membre', 'Ravelo', 'Soa', 'S', phone: '034 10 000 03');
        // A5 : secrétaire dont le compte n'est pas encore validé
        $this->member(
            account: 'secretaire.attente',
            lastName: 'Ravoavy',
            firstNames: 'Mamy',
            voiceGroup: null,
            role: RoleName::Secretary,
            status: UserStatus::Pending,
        );

        // Membres de référence du plan (nom, prénom, voix)
        $this->member('andriamihaja.feno', 'Andriamihaja', 'Feno', 'S', 'S1', phone: '034 20 000 01');
        $this->member('rasoanaivo.hanta', 'Rasoanaivo', 'Hanta', 'A', 'A2', phone: '034 20 000 02');
        $this->member('zafy.lova', 'Zafy', 'Lova', 'T', 'T1', phone: '034 20 000 03');
        $this->member('elysee.noel', 'Élysée', 'Noël', 'S', phone: '034 20 000 04');
        $this->member('rakotoarisoa.hanta', 'Rakotoarisoa', 'Hanta Rasoa', 'A', phone: '034 20 000 05');
        $this->member('rakoto.jean', 'Rakoto', 'Jean', 'B', status: UserStatus::Pending, phone: '034 20 000 06');

        // L4 : membre sans fiche
        $this->member('aaa.paul', 'Aaa', 'Paul', null);
        // L6 : compte sans nom, listé en dernier
        $this->member('sans.nom', null, null, null);
        // V3, M4, M5 : compte en attente qui n'a pas encore de fiche
        $this->member('attente.sans.fiche', 'Randrianasolo', 'Vola', null, status: UserStatus::Pending);

        $this->checkOriginalAdmin();
    }

    // L5 suppose que le compte admin originel existe : il est créé par `php artisan app:deploy`, à lancer avant ce jeu
    private function checkOriginalAdmin(): void
    {
        $mail = config('kantica.super_admin.mail');

        if (blank($mail) || !User::where('mail', $mail)->exists()) {
            $this->command?->warn(
                'L5 non testable : SUPER_ADMIN_MAIL est vide ou son compte n\'existe pas '
                .'(php artisan app:deploy, à lancer avant ce jeu).',
            );
        }
    }
}
