<?php

namespace Database\Seeders;

use App\Models\Song;
use Illuminate\Database\Seeder;

/**
 * Cantiques de démonstration du répertoire (KAN-18) : les 13 cantiques de la maquette 03-Repertoire-et-voix/01-Repertoire,
 * avec leur auteur et leur référence, sans thème (le thème liturgique arrive avec KAN-23).
 *
 * Sert au développement et à la recette tant que l'ajout d'un cantique (KAN-13) n'existe pas. Se lance à la main
 * (`php artisan db:seed --class=SongReferenceSeeder`), jamais par `app:deploy`. Relancé, il ne crée que les titres absents
 * et ne modifie ni ne supprime aucun cantique.
 */
class SongReferenceSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string}> titre, auteur, référence */
    private const array SONGS = [
        ['Adeste fideles', 'John Francis Wade', '012',],
        ['Alma Redemptoris Mater', 'Grégorien', '003',],
        ['Ave Maria', 'Grégorien', '005',],
        ['Il est né le divin Enfant', 'Traditionnel', '041',],
        ['Kyrie XVII', 'Grégorien', '060',],
        ['Les anges dans nos campagnes', 'Traditionnel', '044',],
        ['Minuit, chrétiens', 'Adolphe Adam', '058',],
        ['Ô filii et filiae', 'Jean Tisserand', '071',],
        ['Rorate caeli', 'Grégorien', '083',],
        ['Salve Regina', 'Grégorien', '090',],
        ['Veni Creator Spiritus', 'Raban Maur', '097',],
        ['Venez, divin Messie', 'Abbé Pellegrin', '099',],
        ['Victimae paschali laudes', 'Wipo de Bourgogne', '101',],
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::SONGS as [$title, $author, $reference]) {
            $song = Song::firstOrCreate(['title' => $title,], ['author' => $author, 'reference' => $reference,]);
            $created += $song->wasRecentlyCreated ? 1 : 0;
        }

        $this->command?->info($created.' cantique(s) de démonstration créé(s), '.Song::count().' au répertoire.');
    }
}
