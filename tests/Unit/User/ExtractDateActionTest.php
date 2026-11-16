<?php

namespace Tests\Unit\User;

use App\Actions\User\ExtractDateAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtractDateActionTest extends TestCase
{
    #[DataProvider('texts')]
    public function test_it_finds_the_first_complete_date_day_first(string $text, ?string $date, string $rest): void
    {
        $result = (new ExtractDateAction())($text);

        $this->assertSame($date, $result['date']?->toDateString());
        $this->assertSame($rest, $result['rest']);
    }

    /**
     * @return array<string, array{string, string|null, string}>
     */
    public static function texts(): array
    {
        return [
            'slashes'                  => ['12/03/1998', '1998-03-12', '',],
            'dashes'                   => ['12-03-1998', '1998-03-12', '',],
            'dots'                     => ['12.03.1998', '1998-03-12', '',],
            'iso'                      => ['1998-03-12', '1998-03-12', '',],
            'single digits'            => ['5/3/1998', '1998-03-05', '',],
            'month name'               => ['15 août 2012', '2012-08-15', '',],
            'month name capitalised'   => ['03 Avril 1995', '1995-04-03', '',],
            'accented month'           => ['8 décembre 2020', '2020-12-08', '',],
            'first of the month'       => ['1er mars 2010', '2010-03-01', '',],
            'date then place'          => ['12/03/1998 à Antananarivo', '1998-03-12', 'à Antananarivo',],
            'date then place, no link' => ['02-11-1990 Antsirabe', '1990-11-02', 'Antsirabe',],
            'place then date'          => ['Tana le 5/3/1998', '1998-03-05', 'Tana le',],
            'month and year'           => ['05/1998', null, '05/1998',],
            'month name and year'      => ['Septembre 2019', null, 'Septembre 2019',],
            'year only'                => ['2008', null, '2008',],
            'free text'                => ['Je ne sais plus', null, 'Je ne sais plus',],
            'impossible day'           => ['31/02/2020', null, '31/02/2020',],
            'month above twelve'       => ['12/25/1990', null, '12/25/1990',],
            'unknown month name'       => ['15 foo 2012', null, '15 foo 2012',],
            'empty'                    => ['', null, '',],
        ];
    }
}
