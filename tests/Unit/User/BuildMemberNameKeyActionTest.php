<?php

namespace Tests\Unit\User;

use App\Actions\User\BuildMemberNameKeyAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BuildMemberNameKeyActionTest extends TestCase
{
    #[DataProvider('sameMember')]
    public function test_the_same_member_written_differently_gives_the_same_key(
        string $lastName,
        string $firstName,
    ): void {
        $this->assertSame(
            $this->key('Rakoto', 'Hélène Marie'),
            $this->key($lastName, $firstName),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sameMember(): array
    {
        return [
            'identical'                     => ['Rakoto', 'Hélène Marie',],
            'uppercase last name'           => ['RAKOTO', 'Hélène Marie',],
            'no accent, lowercase'          => ['rakoto', 'helene marie',],
            'hyphen instead of a space'     => ['Rakoto', 'Hélène-Marie',],
            'extra and non breaking spaces' => ["  Rakoto\u{00A0}", 'Hélène   Marie',],
            'punctuation'                   => ['Rakoto.', 'Hélène, Marie',],
        ];
    }

    #[DataProvider('otherMember')]
    public function test_another_member_gives_another_key(string $lastName, string $firstName): void
    {
        $this->assertNotSame($this->key('Rakoto', 'Hélène Marie'), $this->key($lastName, $firstName));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function otherMember(): array
    {
        return [
            'other last name'             => ['Rabe', 'Hélène Marie',],
            'other first name'            => ['Rakoto', 'Hélène',],
            'extra first name'            => ['Rakoto', 'Hélène Marie Claire',],
            'first name in the last name' => ['Rakoto Hélène', 'Marie',],
        ];
    }

    public function test_the_last_name_and_the_first_names_are_not_interchangeable(): void
    {
        $this->assertNotSame($this->key('Fanja', 'Rakoto'), $this->key('Rakoto', 'Fanja'));
    }

    private function key(string $lastName, string $firstName): string
    {
        return (new BuildMemberNameKeyAction())($lastName, $firstName);
    }
}
