<?php

namespace App\Actions\User;

use Illuminate\Support\Str;

// Clé qui identifie un membre par son nom et ses prénoms, pour repérer les doublons : « RAKOTO » + « Fanja Marie »
// et « Rakoto » + « fanja-marie » donnent la même clé (casse, accents, tirets et espaces multiples sont ignorés).
readonly class BuildMemberNameKeyAction
{
    public function __invoke(string $lastName, string $firstName): string
    {
        return $this->normalize($lastName).'|'.$this->normalize($firstName);
    }

    private function normalize(string $text): string
    {
        $text = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))) ?? '';

        return trim($text);
    }
}
