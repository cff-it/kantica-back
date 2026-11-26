<?php

namespace App\Actions\Song;

use App\DTOs\Song\SongSectionDTO;
use App\Models\Song;
use App\Repositories\SongSectionRepository;

// Aligne les sections du cantique sur la liste reçue (liste complète et ordonnée) : crée les sections sans identifiant,
// met à jour celles dont l'identifiant est connu, supprime les autres. La position d'une section est son rang dans la
// liste, de sorte que réordonner revient à renvoyer la liste dans un autre ordre.
// Plusieurs écritures : à appeler dans une transaction.
//
// Une section supprimée disparaît aussi de l'ordre des messes qui l'utilisent (`mass_song_sections`, suppression en
// cascade). Blocage ou retrait : à trancher avec US-18 (point ouvert de KAN-38).
readonly class SyncSongSectionsAction
{
    public function __construct(private SongSectionRepository $sections) {}

    /**
     * @param  list<SongSectionDTO>  $sections
     */
    public function __invoke(Song $song, array $sections): void
    {
        $existing = $this->sections->forSong($song)->keyBy('id');
        $sentIds = array_values(array_filter(array_map(fn (SongSectionDTO $section) => $section->id, $sections)));

        $this->sections->deleteByIds($song, $existing->keys()->diff($sentIds)->values()->all());

        foreach ($sections as $position => $section) {
            $attributes = [
                'type'     => $section->type,
                'number'   => $section->number,
                'lyrics'   => $section->text,
                'position' => $position,
            ];

            if ($section->id === null) {
                $this->sections->create($song, $attributes);
            } else {
                // Identifiant inconnu de ce cantique : ModelNotFoundException (déjà refusé en amont par la requête)
                $this->sections->update($existing->findOrFail($section->id), $attributes);
            }
        }
    }
}
