<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum VoicePart: string
{
    case Soprano = 'S';
    case Alto = 'A';
    case Tenor = 'T';
    case Bass = 'B';

    // Reconnaît « S », « soprano », « Ténor », « Basse »... sans tenir compte de la casse, des accents ni des espaces autour
    public static function fromLabel(string $label): ?self
    {
        return match (Str::lower(Str::ascii(trim($label)))) {
            's', 'soprano' => self::Soprano,
            'a', 'alto' => self::Alto,
            't', 'tenor' => self::Tenor,
            'b', 'basse', 'bass' => self::Bass,
            default => null,
        };
    }
}
