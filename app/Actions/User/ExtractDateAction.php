<?php

namespace App\Actions\User;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

// Trouve la première date complète (jour, mois, année) d'une saisie libre ; le jour vient en premier.
// Formats lus : 12/03/1998, 12-03-1998, 12.03.1998, 1998-03-12, 15 août 2012, 1er mars 2010.
// Une année seule ou un mois et une année (« 05/1998 », « Septembre 2019 ») ne font pas une date complète : rien n'est inventé.
readonly class ExtractDateAction
{
    private const array MONTHS = [
        'janvier'   => 1,
        'fevrier'   => 2,
        'mars'      => 3,
        'avril'     => 4,
        'mai'       => 5,
        'juin'      => 6,
        'juillet'   => 7,
        'aout'      => 8,
        'septembre' => 9,
        'octobre'   => 10,
        'novembre'  => 11,
        'decembre'  => 12,
    ];

    /**
     * @return array{date: CarbonImmutable|null, rest: string} rest : la saisie sans la date trouvée
     */
    public function __invoke(string $text): array
    {
        $patterns = [
            '/(?<!\d)(\d{1,2})[\/.\-](\d{1,2})[\/.\-]((?:19|20)\d{2})(?!\d)/u'    => fn (array $m): array => [$m[3], $m[2], $m[1],],
            '/(?<!\d)((?:19|20)\d{2})-(\d{1,2})-(\d{1,2})(?!\d)/u'                 => fn (array $m): array => [$m[1], $m[2], $m[3],],
            '/(?<!\d)(\d{1,2})(?:er)?\s+(\p{L}+)\.?\s+((?:19|20)\d{2})(?!\d)/u'    => fn (array $m): array => [$m[3], self::MONTHS[Str::ascii(mb_strtolower($m[2]))] ?? 0, $m[1],],
        ];

        foreach ($patterns as $pattern => $toParts) {
            if (preg_match($pattern, $text, $match) !== 1) {
                continue;
            }

            [$year, $month, $day] = array_map('intval', $toParts($match));

            if (!checkdate($month, $day, $year)) {
                continue;
            }

            return [
                'date' => CarbonImmutable::create($year, $month, $day, 0, 0, 0),
                'rest' => $this->collapse((string) preg_replace($pattern, ' ', $text, 1)),
            ];
        }

        return ['date' => null, 'rest' => $this->collapse($text),];
    }

    private function collapse(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
