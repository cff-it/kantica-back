<?php

namespace Tests\Unit\User;

use App\Actions\User\SplitFullNameAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SplitFullNameActionTest extends TestCase
{
    /**
     * @param  array{last_name: string, first_name: string}|null  $expected
     */
    #[DataProvider('names')]
    public function test_it_splits_a_full_name_by_its_capitals(string $fullName, ?array $expected): void
    {
        $this->assertSame($expected, (new SplitFullNameAction())($fullName));
    }

    /**
     * @return array<string, array{string, array{last_name: string, first_name: string}|null}>
     */
    public static function names(): array
    {
        return [
            'last name first'               => ['RAKOTO Fanja', ['last_name' => 'RAKOTO', 'first_name' => 'Fanja',],],
            'several last and first names'  => ['RAKOTO ANDRIA Jean Pierre', ['last_name' => 'RAKOTO ANDRIA', 'first_name' => 'Jean Pierre',],],
            'last name last'                => ['Fanja RAKOTO', ['last_name' => 'RAKOTO', 'first_name' => 'Fanja',],],
            'several names, last name last' => ['Jean Pierre RAKOTO ANDRIA', ['last_name' => 'RAKOTO ANDRIA', 'first_name' => 'Jean Pierre',],],
            'extra spaces'                  => ['  RAKOTO   Fanja  ', ['last_name' => 'RAKOTO', 'first_name' => 'Fanja',],],
            'hyphens'                       => ['RAKOTO-ANDRIA Fanja-Marie', ['last_name' => 'RAKOTO-ANDRIA', 'first_name' => 'Fanja-Marie',],],
            'accented capitals'             => ['ÉLIE Fanja', ['last_name' => 'ÉLIE', 'first_name' => 'Fanja',],],
            'leading capitals win'          => ['RAKOTO Jean PIERRE', ['last_name' => 'RAKOTO', 'first_name' => 'Jean PIERRE',],],
            'no capitals'                   => ['Fanja Rakoto', null,],
            'all lowercase'                 => ['fanja rakoto', null,],
            'last name only'                => ['RAKOTO', null,],
            'all capitals'                  => ['FANJA RAKOTO', null,],
            'first name only'               => ['Fanja', null,],
            'digits only'                   => ['1234 5678', null,],
            'empty'                         => ['', null,],
            'blank'                         => ['   ', null,],
        ];
    }
}
