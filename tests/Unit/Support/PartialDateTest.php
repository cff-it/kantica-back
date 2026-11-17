<?php

namespace Tests\Unit\Support;

use App\Enums\DatePrecision;
use App\Support\PartialDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PartialDateTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('readableInputs')]
    public function test_a_readable_input_gives_the_first_day_of_the_known_period(
        string $input,
        string $expectedDate,
        DatePrecision $expectedPrecision,
        string $expectedText,
    ): void {
        $date = PartialDate::tryParse($input);

        $this->assertNotNull($date);
        $this->assertSame($expectedDate, $date->date->toDateString());
        $this->assertSame($expectedPrecision, $date->precision);
        $this->assertSame($expectedText, $date->toString());
    }

    /**
     * @return array<string, array{string, string, DatePrecision, string}>
     */
    public static function readableInputs(): array
    {
        return [
            'jour jj/mm/aaaa'             => ['12/03/1998', '1998-03-12', DatePrecision::Day, '1998-03-12',],
            'jour sans zéro'              => ['2/3/1998', '1998-03-02', DatePrecision::Day, '1998-03-02',],
            'jour avec tirets'            => ['12-03-1998', '1998-03-12', DatePrecision::Day, '1998-03-12',],
            'jour avec points'            => ['12.03.1998', '1998-03-12', DatePrecision::Day, '1998-03-12',],
            'jour ISO'                    => ['1998-03-12', '1998-03-12', DatePrecision::Day, '1998-03-12',],
            'jour en lettres'             => ['15 août 2012', '2012-08-15', DatePrecision::Day, '2012-08-15',],
            'premier du mois en lettres'  => ['1er mars 2010', '2010-03-01', DatePrecision::Day, '2010-03-01',],
            'mois mm/aaaa'                => ['05/1998', '1998-05-01', DatePrecision::Month, '1998-05',],
            'mois ISO'                    => ['1998-05', '1998-05-01', DatePrecision::Month, '1998-05',],
            'mois en lettres'             => ['Septembre 2019', '2019-09-01', DatePrecision::Month, '2019-09',],
            'mois en lettres sans accent' => ['fevrier 2020', '2020-02-01', DatePrecision::Month, '2020-02',],
            'année seule'                 => ['2008', '2008-01-01', DatePrecision::Year, '2008',],
            'espaces autour'              => ['  2008  ', '2008-01-01', DatePrecision::Year, '2008',],
        ];
    }

    #[DataProvider('unreadableInputs')]
    public function test_an_unreadable_input_gives_null(string $input): void
    {
        $this->assertNull(PartialDate::tryParse($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableInputs(): array
    {
        return [
            'vide'                    => ['',],
            'texte'                   => ['abc',],
            'jour inexistant'         => ['31/02/1998',],
            'jour inexistant en ISO'  => ['1998-02-30',],
            'mois zéro'               => ['00/1998',],
            'mois treize'             => ['13/2019',],
            'mois treize en ISO'      => ['2019-13',],
            'mois inconnu'            => ['Smarch 2019',],
            'année sur deux chiffres' => ['12/03/98',],
            'avant 1900'              => ['1850',],
            'jour et mois sans année' => ['12 mars',],
            'avec une heure'          => ['12/03/1998 10:00',],
        ];
    }

    public function test_a_period_is_in_the_future_only_when_it_starts_after_today(): void
    {
        CarbonImmutable::setTestNow('2026-11-17 10:00:00');

        $this->assertFalse(PartialDate::tryParse('2026')->isFuture());
        $this->assertFalse(PartialDate::tryParse('11/2026')->isFuture());
        $this->assertFalse(PartialDate::tryParse('17/11/2026')->isFuture());
        $this->assertTrue(PartialDate::tryParse('18/11/2026')->isFuture());
        $this->assertTrue(PartialDate::tryParse('12/2026')->isFuture());
        $this->assertTrue(PartialDate::tryParse('2027')->isFuture());
    }

    public function test_a_stored_date_is_rebuilt_with_its_precision(): void
    {
        $this->assertSame('2008', PartialDate::fromStored(CarbonImmutable::create(2008, 1, 1), DatePrecision::Year)->toString());
        $this->assertSame('1998-05', PartialDate::fromStored(Carbon::create(1998, 5, 1), DatePrecision::Month)->toString());
        $this->assertNull(PartialDate::fromStored(null, DatePrecision::Day));

        // Sans précision connue, la date est complète, et l'heure d'une colonne datetime est ignorée
        $this->assertSame('2012-08-15', PartialDate::fromStored(Carbon::create(2012, 8, 15, 14, 30), null)->toString());
    }
}
