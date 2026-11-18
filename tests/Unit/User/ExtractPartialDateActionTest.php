<?php

namespace Tests\Unit\User;

use App\Actions\User\ExtractDateAction;
use App\Actions\User\ExtractPartialDateAction;
use App\Enums\DatePrecision;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtractPartialDateActionTest extends TestCase
{
    #[DataProvider('texts')]
    public function test_it_reads_a_date_known_to_the_day_the_month_or_the_year(
        string $text,
        ?string $date,
        ?DatePrecision $precision,
    ): void {
        $result = (new ExtractPartialDateAction(new ExtractDateAction()))($text);

        $this->assertSame($date, $result?->date->toDateString());
        $this->assertSame($precision, $result?->precision);
    }

    /**
     * @return array<string, array{string, string|null, DatePrecision|null}>
     */
    public static function texts(): array
    {
        return [
            'complete date'              => ['12/03/1998', '1998-03-12', DatePrecision::Day,],
            'complete date, month name'  => ['15 août 2012', '2012-08-15', DatePrecision::Day,],
            'complete date and a place'  => ['12/03/1998 à Tana', '1998-03-12', DatePrecision::Day,],
            'month and year'             => ['05/1998', '1998-05-01', DatePrecision::Month,],
            'month and year, dashes'     => ['5-1998', '1998-05-01', DatePrecision::Month,],
            'month name and year'        => ['Septembre 2019', '2019-09-01', DatePrecision::Month,],
            'accented month name'        => ['août 2012', '2012-08-01', DatePrecision::Month,],
            'iso month'                  => ['1998-05', '1998-05-01', DatePrecision::Month,],
            'year only'                  => ['2008', '2008-01-01', DatePrecision::Year,],
            'year in a sentence'         => ['en 2008', '2008-01-01', DatePrecision::Year,],
            'month and year in a phrase' => ['vers septembre 2019', '2019-09-01', DatePrecision::Month,],
            'free text'                  => ['Je ne sais plus', null, null,],
            'impossible day'             => ['31/02/2020', null, null,],
            'month above twelve'         => ['13/2008', null, null,],
            'two digits year'            => ['12/03/98', null, null,],
            'year before 1900'           => ['1850', null, null,],
            'empty'                      => ['', null, null,],
        ];
    }
}
