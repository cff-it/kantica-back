<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Enums\VoicePart;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function all(): Collection
    {
        return User::with('firstnames')->orderBy('last_name')->get();
    }

    // Membres actifs triés par nom, éventuellement d'une seule voix. Ne font pas partie des membres : les comptes en
    // attente et le compte administrateur originel (compte technique, identifié par SUPER_ADMIN_MAIL).
    // Requête commune à l'export et à la liste de la secrétaire (US-08).
    public function members(?VoicePart $voice = null): Collection
    {
        $superAdminMail = config('kantica.super_admin.mail');

        return User::with(['firstnames', 'profile.voiceGroup', 'profile.voice',])
            ->where('status', UserStatus::Active->value)
            ->when(filled($superAdminMail), fn ($query) => $query->where('mail', '!=', $superAdminMail))
            ->when($voice, fn ($query) => $query->whereHas(
                'profile.voiceGroup',
                fn ($groups) => $groups->where('abbreviation', $voice->value),
            ))
            ->orderBy('last_name')
            ->orderBy('id')
            ->get();
    }

    // Compte seul, sans relation : pour vérifier qu'il existe (404 sinon) avant une écriture ciblée
    public function findPlainById(int $id): User
    {
        return User::findOrFail($id);
    }

    public function findById(int $id): User
    {
        return User::with(['firstnames', 'profile.voiceGroup', 'profile.voice',])->findOrFail($id);
    }

    // Fiche de recensement complète (écran « Modifier la fiche ») : en plus de findById(), les sacrements, l'engagement
    // et le contact d'urgence, qui ne sont pas nécessaires ailleurs
    public function findRecordById(int $id): User
    {
        return User::with([
            'firstnames',
            'profile.voiceGroup',
            'profile.voice',
            'sacraments',
            'engagement',
            'emergencyContacts',
        ])->findOrFail($id);
    }

    public function findByMail(string $mail): ?User
    {
        return User::where('mail', $mail)->first();
    }

    public function existsWithRole(string $role): bool
    {
        return User::role($role)->exists();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    // Compte créé par la chorale (import) : actif et email considéré comme vérifié, sans lien envoyé
    public function createActive(array $data): User
    {
        $user = new User($data);
        $user->forceFill([
            'status'            => UserStatus::Active,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * @param  list<string>  $mails  emails en minuscules
     * @return list<string> emails (en minuscules) déjà utilisés par un compte
     */
    public function existingMails(array $mails): array
    {
        return User::whereIn(DB::raw('lower(mail)'), $mails)
            ->pluck('mail')
            ->map(fn (string $mail): string => mb_strtolower($mail))
            ->all();
    }

    /**
     * Nom et prénoms de tous les comptes, pour repérer les doublons à l'import (la chorale compte quelques centaines de membres).
     * default_first_name : prénom par défaut seul ; first_names : tous les prénoms à la suite, dans l'ordre.
     *
     * @return list<array{last_name: string, default_first_name: string|null, first_names: string}>
     */
    public function memberNames(): array
    {
        return User::query()
            ->select(['id', 'last_name',])
            ->with('firstnames')
            ->get()
            ->map(fn (User $user): array => [
                'last_name'          => $user->last_name,
                'default_first_name' => $user->firstnames->firstWhere('default', true)?->first_name,
                'first_names'        => $user->firstnames->pluck('first_name')->implode(' '),
            ])
            ->all();
    }

    // Données nécessaires au front pour router l'utilisateur courant et afficher sa fiche
    public function loadCurrentUser(User $user): User
    {
        return $user->loadMissing([
            'roles',
            'firstnames',
            'profile.voiceGroup',
            'profile.voice',
        ]);
    }

    // Retourne true uniquement si le compte vient de passer de « en attente » à « actif »
    public function activate(User $user): bool
    {
        $updated = User::whereKey($user->id)
            ->where('status', UserStatus::Pending->value)
            ->update(['status' => UserStatus::Active->value,]);

        return $updated === 1;
    }
}
