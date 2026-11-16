<?php

namespace Tests\Unit\User;

use App\Actions\User\ExtractDateAction;
use App\Actions\User\ParseBirthAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParseBirthActionTest extends TestCase
{
    #[DataProvider('births')]
    public function test_it_splits_the_birth_into_date_and_place(string $text, ?string $date, ?string $place, ?string $warning): void
    {
        $result = (new ParseBirthAction(new ExtractDateAction()))($text);

        $this->assertSame($date, $result['date']?->toDateString());
        $this->assertSame($place, $result['place']);
        $this->assertSame($warning, $result['warning']);
    }

    /**
     * @return array<string, array{string, string|null, string|null, string|null}>
     */
    public static function births(): array
    {
        return [
            'date and place'          => ['12/03/1998 à Antananarivo', '1998-03-12', 'Antananarivo', null,],
            'date and place, no link' => ['02-11-1990 Antsirabe', '1990-11-02', 'Antsirabe', null,],
            'born on, in'             => ['né le 12/03/1998 à Tana', '1998-03-12', 'Tana', null,],
            'date with a month name'  => ['12 mars 1998', '1998-03-12', null, null,],
            'place with a capital A'  => ['12/03/1998 Ambohijatovo', '1998-03-12', 'Ambohijatovo', null,],
            'place only'              => ['Antananarivo', null, 'Antananarivo', null,],
            'empty'                   => ['', null, null, null,],
            'year and place'          => [
                '1995, Fianarantsoa',
                null,
                'Fianarantsoa',
                'Date de naissance « 1995 » incomplète (jour, mois et année attendus) : non enregistrée.',
            ],
            'month name, year, place' => [
                'Septembre 1995 Tana',
                null,
                'Tana',
                'Date de naissance « Septembre 1995 » incomplète (jour, mois et année attendus) : non enregistrée.',
            ],
            'year only'               => [
                '1995',
                null,
                null,
                'Date de naissance « 1995 » incomplète (jour, mois et année attendus) : non enregistrée.',
            ],
            'digits, no usable date'  => [
                '12/03/98 Tana',
                null,
                null,
                'Naissance « 12/03/98 Tana » non reconnue (exemple attendu : 12/03/1998 à Antananarivo) : non enregistrée.',
            ],
        ];
    }
}
