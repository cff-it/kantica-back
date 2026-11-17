<?php

namespace Tests\Unit\User;

use App\Actions\User\ExtractDateAction;
use App\Actions\User\ExtractDriveFileIdAction;
use App\Actions\User\ExtractPartialDateAction;
use App\Actions\User\ParseBirthAction;
use App\Actions\User\ParseRecensementFieldsAction;
use App\Enums\DatePrecision;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParseRecensementFieldsActionTest extends TestCase
{
    private const array FIELDS = [
        'address',
        'birth',
        'registration_number',
        'picture_url',
        'social_pseudo',
        'baptism_date',
        'baptism_place',
        'first_communion_date',
        'first_communion_place',
        'confirmation_date',
        'confirmation_place',
        'adhesion_date',
        'engagement_date',
        'engagement_place',
        'spouse',
        'marriage_date',
        'marriage_place',
        'emergency_name',
        'emergency_phone',
        'emergency_relationship',
    ];

    public function test_an_empty_row_gives_no_information_and_no_warning(): void
    {
        $result = $this->parse([]);

        $this->assertSame([], $result['warnings']);
        $this->assertNull($result['address']);
        $this->assertNull($result['birth_date']);
        $this->assertNull($result['spouse_name']);
        $this->assertFalse($result['spouse_is_member']);
        $this->assertNull($result['emergency_name']);
    }

    #[DataProvider('spouses')]
    public function test_it_tells_whether_the_spouse_is_a_member(string $spouse, bool $isMember): void
    {
        $result = $this->parse(['spouse' => $spouse,]);

        $this->assertSame($spouse, $result['spouse_name']);
        $this->assertSame($isMember, $result['spouse_is_member']);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function spouses(): array
    {
        return [
            'member of the choir'   => ['RABE Exemple, membre de la CFF', true,],
            'choir in brackets'     => ['RASOA Exemple (CFF)', true,],
            'member, lowercase'     => ['rabe exemple membre', true,],
            'no mention'            => ['RABE Exemple (mari)', false,],
            'not a member'          => ['RABE Exemple, non membre', false,],
            'not member of the CFF' => ['RABE Exemple, pas membre de la CFF', false,],
        ];
    }

    public function test_a_phone_without_a_contact_name_is_not_kept(): void
    {
        $result = $this->parse(['emergency_phone' => '034 00 000 02',]);

        $this->assertNull($result['emergency_phone']);
        $this->assertSame(["Numéro du contact d'urgence sans nom : non enregistré.",], $result['warnings']);
    }

    public function test_a_contact_keeps_its_name_and_phone_as_typed(): void
    {
        $result = $this->parse([
            'emergency_name'  => 'RAKOTO Papa, RAKOTO Maman',
            'emergency_phone' => '034 00 000 02 / 033 00 000 03',
        ]);

        $this->assertSame('RAKOTO Papa, RAKOTO Maman', $result['emergency_name']);
        $this->assertSame('034 00 000 02 / 033 00 000 03', $result['emergency_phone']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_a_contact_keeps_its_relationship(): void
    {
        $result = $this->parse(['emergency_name' => 'RAKOTO Papa', 'emergency_relationship' => 'Parents',]);

        $this->assertSame('Parents', $result['emergency_relationship']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_a_relationship_without_a_contact_name_is_not_kept(): void
    {
        $result = $this->parse(['emergency_relationship' => 'Parents',]);

        $this->assertNull($result['emergency_relationship']);
        $this->assertSame(["Type de contact d'urgence « Parents » sans nom : non enregistré.",], $result['warnings']);
    }

    public function test_the_social_pseudo_is_kept_as_typed(): void
    {
        $result = $this->parse(['social_pseudo' => 'Pseudo Exemple',]);

        $this->assertSame('Pseudo Exemple', $result['social_pseudo']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_a_sacrament_with_a_date_and_a_place_is_kept(): void
    {
        $result = $this->parse([
            'baptism_date'       => '05/06/1998',
            'baptism_place'      => 'Ecar Faravohitra',
            'confirmation_date'  => '15 août 2012',
        ]);

        $this->assertSame(['baptism', 'confirmation',], array_keys($result['sacraments']));
        $this->assertSame('1998-06-05', $result['sacraments']['baptism']['date']->date->toDateString());
        $this->assertSame(DatePrecision::Day, $result['sacraments']['baptism']['date']->precision);
        $this->assertSame('Ecar Faravohitra', $result['sacraments']['baptism']['place']);
        $this->assertSame('2012-08-15', $result['sacraments']['confirmation']['date']->date->toDateString());
        $this->assertNull($result['sacraments']['confirmation']['place']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_a_sacrament_with_only_a_place_is_kept_without_date(): void
    {
        $result = $this->parse(['first_communion_place' => 'Cathédrale Andohalo',]);

        $this->assertSame(['first_communion',], array_keys($result['sacraments']));
        $this->assertNull($result['sacraments']['first_communion']['date']);
        $this->assertSame('Cathédrale Andohalo', $result['sacraments']['first_communion']['place']);
    }

    public function test_a_partial_date_is_kept_with_its_precision(): void
    {
        $result = $this->parse([
            'baptism_date'         => '05/1998',
            'first_communion_date' => '2008',
            'confirmation_date'    => 'Septembre 2019',
            'adhesion_date'        => '2019',
            'engagement_date'      => '03/2020',
            'marriage_date'        => '2021',
        ]);

        $this->assertSame([], $result['warnings']);

        $baptism = $result['sacraments']['baptism']['date'];
        $this->assertSame('1998-05-01', $baptism->date->toDateString());
        $this->assertSame(DatePrecision::Month, $baptism->precision);

        $firstCommunion = $result['sacraments']['first_communion']['date'];
        $this->assertSame('2008-01-01', $firstCommunion->date->toDateString());
        $this->assertSame(DatePrecision::Year, $firstCommunion->precision);

        $confirmation = $result['sacraments']['confirmation']['date'];
        $this->assertSame('2019-09-01', $confirmation->date->toDateString());
        $this->assertSame(DatePrecision::Month, $confirmation->precision);

        $this->assertSame('2019-01-01', $result['adhesion_date']->date->toDateString());
        $this->assertSame(DatePrecision::Year, $result['adhesion_date']->precision);
        $this->assertSame('2020-03-01', $result['engagement_date']->date->toDateString());
        $this->assertSame(DatePrecision::Month, $result['engagement_date']->precision);
        $this->assertSame('2021-01-01', $result['marriage_date']->date->toDateString());
        $this->assertSame(DatePrecision::Year, $result['marriage_date']->precision);
    }

    public function test_an_unreadable_date_is_not_kept_and_is_reported(): void
    {
        $result = $this->parse(['baptism_date' => 'Je ne sais plus', 'first_communion_date' => '31/02/2008',]);

        // Sans date lisible ni lieu, aucune ligne `user_sacraments` n'est créée
        $this->assertSame([], $result['sacraments']);
        $this->assertSame([
            'Date du baptême « Je ne sais plus » non reconnue (jj/mm/aaaa, mm/aaaa ou aaaa attendus) : non enregistrée.',
            'Date de la première communion « 31/02/2008 » non reconnue (jj/mm/aaaa, mm/aaaa ou aaaa attendus) : non enregistrée.',
        ], $result['warnings']);
    }

    public function test_the_birth_date_stays_complete_only(): void
    {
        $result = $this->parse(['birth' => '05/1998 Tana',]);

        $this->assertNull($result['birth_date']);
        $this->assertSame('Tana', $result['birth_place']);
        $this->assertSame(
            ['Date de naissance « 05/1998 » incomplète (jour, mois et année attendus) : non enregistrée.',],
            $result['warnings'],
        );
    }

    public function test_no_sacrament_gives_no_row(): void
    {
        $this->assertSame([], $this->parse([])['sacraments']);
    }

    public function test_a_picture_that_is_not_a_link_is_not_kept(): void
    {
        $result = $this->parse(['picture_url' => 'pas de photo',]);

        $this->assertNull($result['picture_url']);
        $this->assertSame(['Photo « pas de photo » : seuls les liens Google Drive sont repris, non enregistrée.',], $result['warnings']);
    }

    public function test_a_link_that_is_not_google_drive_is_not_kept(): void
    {
        $result = $this->parse(['picture_url' => 'https://exemple.com/photo.jpg',]);

        $this->assertNull($result['picture_url']);
        $this->assertSame(
            ['Photo « https://exemple.com/photo.jpg » : seuls les liens Google Drive sont repris, non enregistrée.',],
            $result['warnings'],
        );
    }

    public function test_a_google_drive_link_is_kept_as_it_was_written(): void
    {
        $result = $this->parse(['picture_url' => 'https://drive.google.com/open?id=abc123',]);

        $this->assertSame('https://drive.google.com/open?id=abc123', $result['picture_url']);
        $this->assertSame([], $result['warnings']);
    }

    /**
     * @param  array<string, string>  $values  champs renseignés ; les autres sont vides
     * @return array<string, mixed>
     */
    private function parse(array $values): array
    {
        $extractDate = new ExtractDateAction();
        $action = new ParseRecensementFieldsAction(
            new ExtractPartialDateAction($extractDate),
            new ParseBirthAction($extractDate),
            new ExtractDriveFileIdAction(),
        );

        return $action([...array_fill_keys(self::FIELDS, ''), ...$values,]);
    }
}
