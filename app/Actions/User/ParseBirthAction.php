<?php

namespace App\Actions\User;

use Carbon\CarbonImmutable;

// Sépare « 12/03/1998 à Antananarivo » en date et lieu de naissance (une seule colonne dans le formulaire).
// Ce qui ne se lit pas est signalé dans `warning` et n'est pas enregistré : le compte est créé sans.
readonly class ParseBirthAction
{
    // Année seule, ou mois et année : « 1995 », « 05/1998 », « Septembre 2019 »
    private const string PARTIAL_DATE = '/(?<!\d)(?:\d{1,2}[\/.\-]|(?:janvier|f[ée]vrier|mars|avril|mai|juin|juillet|ao[uû]t|septembre|octobre|novembre|d[ée]cembre)\s+)?(?:19|20)\d{2}(?!\d)/iu';

    public function __construct(
        private ExtractDateAction $extractDate,
    ) {}

    /**
     * @return array{date: CarbonImmutable|null, place: string|null, warning: string|null}
     */
    public function __invoke(string $text): array
    {
        if ($text === '') {
            return ['date' => null, 'place' => null, 'warning' => null,];
        }

        $found = ($this->extractDate)($text);

        if ($found['date'] !== null) {
            return ['date' => $found['date'], 'place' => $this->place($found['rest']), 'warning' => null,];
        }

        // « 1995, Fianarantsoa » : la date est incomplète, le lieu est gardé
        if (preg_match(self::PARTIAL_DATE, $text, $match) === 1) {
            return [
                'date'    => null,
                'place'   => $this->place((string) preg_replace(self::PARTIAL_DATE, ' ', $text, 1)),
                'warning' => 'Date de naissance « '.$match[0].' » incomplète (jour, mois et année attendus) : non enregistrée.',
            ];
        }

        // Des chiffres sans date reconnaissable (« 12/03/98 Tana ») : impossible de séparer la date du lieu
        if (preg_match('/\d/', $text) === 1) {
            return [
                'date'    => null,
                'place'   => null,
                'warning' => 'Naissance « '.$text.' » non reconnue (exemple attendu : 12/03/1998 à Antananarivo) : non enregistrée.',
            ];
        }

        // Aucun chiffre : la saisie est lue comme le seul lieu
        return ['date' => null, 'place' => $text, 'warning' => null,];
    }

    // Retire la ponctuation et les mots de liaison autour du lieu : « né le 12/03/1998 à Tana » devient « Tana »
    private function place(string $text): ?string
    {
        $place = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $place = preg_replace('/^(?:[\s,;:.\-\/]+|(?:né|née|nee|ne|le|à|a|au|en|vers)(?![\p{L}\d]))+/iu', '', $place) ?? $place;
        $place = preg_replace('/[\s,;:.\-\/]+$/u', '', $place) ?? $place;

        return $place === '' ? null : $place;
    }
}
