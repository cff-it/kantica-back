<?php

namespace Tests\Feature\Member\Concerns;

trait BuildsMemberRecords
{
    /**
     * Payload valide de PUT /api/members/{id} : la fiche complète de l'écran « Modifier la fiche », avec des dates
     * partielles (adhésion au mois, baptême au mois, première communion à l'année) et sans mariage.
     *
     * @param  array<string, mixed>  $overrides  remplace des clés de premier niveau (un bloc se remplace en entier)
     * @return array<string, mixed>
     */
    protected function recordPayload(array $overrides = []): array
    {
        return [
            'last_name'                  => 'Razafy',
            'first_name'                 => 'Hantaniaina',
            'voice_group'                => 'B',
            'voice'                      => 'B1',
            'phone'                      => '032 98 765 43',
            'birth_date'                 => '12/03/1998',
            'birth_place'                => 'Antananarivo',
            'address'                    => 'Lot IVB 12 Faravohitra, Antananarivo',
            'social_pseudo'              => 'Hanta Rasoa',
            'region_registration_number' => '1234 Faravohitra',
            'adhesion_date'              => 'Septembre 2019',
            'engagement_date'            => '08/12/2020',
            'engagement_place'           => 'Ecar Faravohitra',
            'baptism'                    => ['date' => '05/1998', 'place' => 'Ecar Faravohitra',],
            'first_communion'            => ['date' => '2008', 'place' => 'Ecar Faravohitra',],
            'confirmation'               => ['date' => '15/08/2012', 'place' => 'Cathédrale Andohalo',],
            'marriage'                   => ['spouse' => null, 'date' => null, 'place' => null,],
            'emergency_contact'          => [
                'relationships' => ['Parents',],
                'name'          => 'RASOANAIVO Papa, RASOANAIVO Maman',
                'phone'         => '034 00 000 02 / 033 00 000 03',
            ],
            ...$overrides,
        ];
    }

    /**
     * Fiche attendue dans `data.profile` après l'écriture de `recordPayload()` : les dates sont écrites au format de
     * la précision connue (1998-03-12, 1998-05 ou 2008) et l'email est celui du compte.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function expectedRecord(string $contactMail, array $overrides = []): array
    {
        return [
            'last_name'                  => 'Razafy',
            'first_name'                 => 'Hantaniaina',
            'voice_group'                => 'B',
            'voice'                      => 'B1',
            'phone'                      => '032 98 765 43',
            'contact_mail'               => $contactMail,
            'completed'                  => true,
            'birth_date'                 => '1998-03-12',
            'birth_place'                => 'Antananarivo',
            'address'                    => 'Lot IVB 12 Faravohitra, Antananarivo',
            'social_pseudo'              => 'Hanta Rasoa',
            'region_registration_number' => '1234 Faravohitra',
            'picture_url'                => null,
            'adhesion_date'              => '2019-09',
            'engagement_date'            => '2020-12-08',
            'engagement_place'           => 'Ecar Faravohitra',
            'baptism'                    => ['date' => '1998-05', 'place' => 'Ecar Faravohitra',],
            'first_communion'            => ['date' => '2008', 'place' => 'Ecar Faravohitra',],
            'confirmation'               => ['date' => '2012-08-15', 'place' => 'Cathédrale Andohalo',],
            'marriage'                   => ['spouse' => null, 'date' => null, 'place' => null,],
            'emergency_contact'          => [
                'relationships' => ['Parents',],
                'name'          => 'RASOANAIVO Papa, RASOANAIVO Maman',
                'phone'         => '034 00 000 02 / 033 00 000 03',
            ],
            ...$overrides,
        ];
    }
}
