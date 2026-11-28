<?php

namespace App\Actions\Song;

use App\DTOs\Song\SongSectionDTO;
use App\Models\Song;
use App\Repositories\SongSectionRepository;
use Illuminate\Validation\ValidationException;

// Aligne les sections du cantique sur la liste reçue (liste complète et ordonnée) : crée les sections sans identifiant,
// met à jour celles dont l'identifiant est connu, supprime les autres. La position d'une section est son rang dans la
// liste, de sorte que réordonner revient à renvoyer la liste dans un autre ordre.
// Plusieurs écritures : à appeler dans une transaction.
//
// Une section utilisée dans l'ordre d'une messe (`mass_song_sections`, US-18) ne peut pas être supprimée : la cascade SQL
// effacerait en silence une partie du programme de messes préparées ou déjà chantées. Elle peut en revanche être modifiée
// ou déplacée. Le blocage reste à confirmer avec la présidente avant US-18 (point ouvert de KAN-38) ; le lever revient à
// retirer le contrôle ci-dessous.
readonly class SyncSongSectionsAction
{
    public function __construct(private SongSectionRepository $sections) {}

    /**
     * @param  list<SongSectionDTO>  $sections
     *
     * @throws ValidationException si une section à supprimer est utilisée dans l'ordre d'une messe, ou si une section de
     *                             la liste n'existe plus (supprimée par une modification simultanée)
     */
    public function __invoke(Song $song, array $sections): void
    {
        $existing = $this->sections->forSong($song)->keyBy('id');
        $sentIds = array_values(array_filter(array_map(fn (SongSectionDTO $section) => $section->id, $sections)));
        $removedIds = $existing->keys()->diff($sentIds)->values()->all();

        if ($this->sections->idsUsedInMasses($song, $removedIds) !== []) {
            throw ValidationException::withMessages([
                'sections' => 'Une section utilisée dans l\'ordre d\'une messe ne peut pas être supprimée.',
            ]);
        }

        $this->sections->deleteByIds($song, $removedIds);

        foreach ($sections as $position => $section) {
            $attributes = [
                'type'     => $section->type,
                'number'   => $section->number,
                'lyrics'   => $section->text,
                'position' => $position,
            ];

            if ($section->id === null) {
                $this->sections->create($song, $attributes);

                continue;
            }

            // La requête a déjà vérifié que la section appartient au cantique, mais une modification simultanée a pu la
            // supprimer entre la validation et le verrou du cantique : 422 sur cette section, pas un 404 « cantique
            // introuvable » alors que le cantique existe
            $current = $existing->get($section->id);

            if ($current === null) {
                throw ValidationException::withMessages([
                    "sections.{$position}.id" => 'Cette section n\'existe plus : rechargez le cantique.',
                ]);
            }

            $this->sections->update($current, $attributes);
        }
    }
}
