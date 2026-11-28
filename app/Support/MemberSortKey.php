<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Ordre d'affichage des membres (liste des membres, saisie des présences) : nom puis prénom, sans tenir compte de la
 * casse ni des accents ; les fiches sans nom en dernier. L'identifiant départage les homonymes.
 */
final class MemberSortKey
{
    /**
     * @return array{0: int, 1: string, 2: string, 3: int}
     */
    public static function of(User $member): array
    {
        return [
            blank($member->last_name) ? 1 : 0,
            self::normalize((string) $member->last_name),
            self::normalize($member->firstnames->pluck('first_name')->implode(' ')),
            $member->id,
        ];
    }

    private static function normalize(string $value): string
    {
        return Str::ascii(Str::lower($value));
    }
}
