<?php

namespace App\Actions\User;

// Sépare « RAKOTO Fanja » (ou « Fanja RAKOTO ») en nom de famille et prénom(s), d'après les majuscules.
// Le nom de famille est le bloc de mots entièrement en majuscules, en tête ou en fin de saisie.
// Si rien ne permet de trancher (« Fanja Rakoto », « RAKOTO », « FANJA RAKOTO »), rien n'est deviné.
readonly class SplitFullNameAction
{
    /**
     * @return array{last_name: string, first_name: string}|null null si la saisie ne peut pas être séparée
     */
    public function __invoke(string $fullName): ?array
    {
        $words = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($words);

        $leading = 0;

        while ($leading < $count && $this->isUppercase($words[$leading])) {
            $leading++;
        }

        // « RAKOTO ANDRIA Jean Pierre » : le nom de famille vient en premier
        if ($leading > 0 && $leading < $count) {
            return $this->result(array_slice($words, 0, $leading), array_slice($words, $leading));
        }

        // « Jean Pierre RAKOTO ANDRIA » : le nom de famille vient en dernier (sans majuscule en tête)
        $trailing = 0;

        while ($trailing < $count && $this->isUppercase($words[$count - 1 - $trailing])) {
            $trailing++;
        }

        if ($leading === 0 && $trailing > 0 && $trailing < $count) {
            return $this->result(array_slice($words, $count - $trailing), array_slice($words, 0, $count - $trailing));
        }

        return null;
    }

    // Au moins une lettre, et aucune minuscule : « RAKOTO-ANDRIA » et « ÉLIE » oui, « Jean » et « 12 » non
    private function isUppercase(string $word): bool
    {
        return preg_match('/\p{L}/u', $word) === 1 && mb_strtoupper($word) === $word;
    }

    /**
     * @param  list<string>  $lastName
     * @param  list<string>  $firstName
     * @return array{last_name: string, first_name: string}
     */
    private function result(array $lastName, array $firstName): array
    {
        return [
            'last_name'  => implode(' ', $lastName),
            'first_name' => implode(' ', $firstName),
        ];
    }
}
