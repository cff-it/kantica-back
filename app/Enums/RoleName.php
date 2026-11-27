<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Secretary = 'secretaire';
    case Member = 'membre';

    /**
     * Rôle le plus élevé parmi des noms de rôles (Admin, puis Secrétaire, puis Membre), ou null s'il n'y en a aucun.
     * Un compte n'a qu'un rôle depuis KAN-35 ; les anciens comptes à plusieurs rôles affichent le plus élevé.
     *
     * @param  iterable<string>  $names
     */
    public static function highestOf(iterable $names): ?self
    {
        $names = collect($names);

        foreach (self::cases() as $role) {
            if ($names->contains($role->value)) {
                return $role;
            }
        }

        return null;
    }
}
