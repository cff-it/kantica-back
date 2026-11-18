<?php

namespace App\Actions\User;

use App\Enums\DatePrecision;
use App\Support\PartialDate;

// Trouve la date d'une saisie libre, au jour, au mois ou à l'année seulement : « 05/06/1998 », « 05/1998 »,
// « Septembre 2019 », « 2008 ». Une date complète lue par ExtractDateAction passe en premier ; sinon un mois et une
// année, ou une année seule. Une saisie qui ne se lit pas donne null : rien n'est inventé.
readonly class ExtractPartialDateAction
{
    // Mois et année, ou année seule. Le lookbehind et le lookahead écartent un morceau de date plus longue :
    // « 31/02/2020 » (jour impossible) n'est pas lu comme « 02/2020 », ni « 1998-05 » comme « 1998 ».
    private const string MONTH_YEAR_OR_YEAR = '/(?<![\d\/.\-])(?:\d{1,2}[\/.\-]|(?:janvier|f[ée]vrier|mars|avril|mai|juin|juillet|ao[uû]t|septembre|octobre|novembre|d[ée]cembre)\s+)?(?:19|20)\d{2}(?!\d)(?![\/.\-]\d)/iu';

    public function __construct(
        private ExtractDateAction $extractDate,
    ) {}

    public function __invoke(string $text): ?PartialDate
    {
        $complete = ($this->extractDate)($text)['date'];

        if ($complete !== null) {
            return new PartialDate($complete, DatePrecision::Day);
        }

        // Saisie qui est toute la date : « 1998-05 » (mois ISO), « 2008 », « Septembre 2019 »
        $whole = PartialDate::tryParse(trim($text));

        if ($whole !== null) {
            return $whole;
        }

        if (preg_match(self::MONTH_YEAR_OR_YEAR, $text, $match) !== 1) {
            return null;
        }

        // Null aussi pour un mois impossible (« 13/2008 ») : la saisie est signalée, pas corrigée
        return PartialDate::tryParse($match[0]);
    }
}
