<?php

namespace App\Support;

use App\Enums\DatePrecision;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Date connue au jour, au mois ou à l'année seulement.
 *
 * En base, la colonne `date` garde le premier jour de la période (« 05/1998 » donne 1998-05-01, « 2008 » donne
 * 2008-01-01) et une colonne de précision dit ce qui est réellement connu. Rien n'est inventé : sans la précision,
 * 2008-01-01 serait pris pour un 1er janvier.
 *
 * Saisies lues : 12/03/1998, 12-03-1998, 12.03.1998, 1998-03-12, 15 août 2012, 05/1998, 1998-05, Septembre 2019, 2008.
 * Écriture (`toString()`) : 1998-03-12, 1998-05 ou 2008.
 */
readonly class PartialDate
{
    private const int MIN_YEAR = 1900;

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

    public function __construct(
        public CarbonImmutable $date,
        public DatePrecision $precision,
    ) {}

    // Null si la saisie n'est ni une date valide ni une année, un mois ou un jour lisibles
    public static function tryParse(string $input): ?self
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $input));
        $parts = self::parts($text);

        if ($parts === null) {
            return null;
        }

        [$year, $month, $day] = $parts;
        $precision = match (true) {
            $month === null => DatePrecision::Year,
            $day === null   => DatePrecision::Month,
            default         => DatePrecision::Day,
        };

        if ($year < self::MIN_YEAR || !checkdate($month ?? 1, $day ?? 1, $year)) {
            return null;
        }

        return new self(CarbonImmutable::create($year, $month ?? 1, $day ?? 1, 0, 0, 0), $precision);
    }

    // Reconstitue la date lue en base ; null quand la date n'est pas renseignée
    public static function fromStored(?CarbonInterface $date, ?DatePrecision $precision): ?self
    {
        if ($date === null) {
            return null;
        }

        return new self(CarbonImmutable::instance($date)->startOfDay(), $precision ?? DatePrecision::Day);
    }

    // La période commence après aujourd'hui : « 2026 » n'est pas dans le futur en 2026, « 12/2026 » l'est en novembre
    public function isFuture(): bool
    {
        return $this->date->greaterThan(CarbonImmutable::today());
    }

    // Écriture de la réponse API : 1998-03-12, 1998-05 ou 2008
    public function toString(): string
    {
        return $this->date->format(match ($this->precision) {
            DatePrecision::Day   => 'Y-m-d',
            DatePrecision::Month => 'Y-m',
            DatePrecision::Year  => 'Y',
        });
    }

    /**
     * @return array{0: int, 1: int|null, 2: int|null}|null année, puis mois et jour quand ils sont connus
     */
    private static function parts(string $text): ?array
    {
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $text, $m) === 1) {
            return [(int) $m[3], (int) $m[2], (int) $m[1],];
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m) === 1) {
            return [(int) $m[1], (int) $m[2], (int) $m[3],];
        }

        if (preg_match('/^(\d{1,2})(?:er)?\s(\p{L}+)\.?\s(\d{4})$/u', $text, $m) === 1) {
            return [(int) $m[3], self::month($m[2]), (int) $m[1],];
        }

        if (preg_match('/^(\d{1,2})[\/.\-](\d{4})$/', $text, $m) === 1) {
            return [(int) $m[2], (int) $m[1], null,];
        }

        if (preg_match('/^(\d{4})-(\d{1,2})$/', $text, $m) === 1) {
            return [(int) $m[1], (int) $m[2], null,];
        }

        if (preg_match('/^(\p{L}+)\.?\s(\d{4})$/u', $text, $m) === 1) {
            return [(int) $m[2], self::month($m[1]), null,];
        }

        if (preg_match('/^(\d{4})$/', $text, $m) === 1) {
            return [(int) $m[1], null, null,];
        }

        return null;
    }

    // 0 pour un mois inconnu : checkdate() le refuse
    private static function month(string $name): int
    {
        return self::MONTHS[Str::ascii(mb_strtolower($name))] ?? 0;
    }
}
