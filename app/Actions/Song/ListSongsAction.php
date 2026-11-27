<?php

namespace App\Actions\Song;

use App\DTOs\Song\ListSongsDTO;
use App\DTOs\Song\SongListResultDTO;
use App\Models\Song;
use App\Repositories\SongRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

readonly class ListSongsAction
{
    private const int PER_PAGE = 20;

    public function __construct(private SongRepository $songs) {}

    /*
     * Comme la liste des membres (ListMembersAction) : recherche, tri et pagination en PHP sur tout le répertoire
     * (quelques centaines de cantiques), pour une recherche insensible aux accents identique sur PostgreSQL et SQLite.
     * Au-delà de quelques milliers de cantiques, filtrer et paginer en SQL (extension `unaccent` de PostgreSQL).
     */
    public function __invoke(ListSongsDTO $dto): SongListResultDTO
    {
        $all = $this->songs->all();

        $matching = $all
            ->when(filled($dto->search), fn (Collection $songs) => $songs->filter(
                fn (Song $song) => $this->matches($song, $dto->search),
            ))
            ->sortBy(fn (Song $song) => $this->sortKey($song))
            ->values();

        $page = new LengthAwarePaginator(
            $matching->forPage($dto->page, self::PER_PAGE)->values(),
            $matching->count(),
            self::PER_PAGE,
            $dto->page,
            ['path' => Paginator::resolveCurrentPath(),],
        );

        return new SongListResultDTO(
            songs: $page->withQueryString(),
            songsCount: $all->count(),
        );
    }

    // Tous les termes saisis doivent se retrouver dans le titre ou l'auteur (« adeste wade » fonctionne), comme le
    // champ « Titre ou auteur » de la maquette : ni la référence ni les paroles
    private function matches(Song $song, string $search): bool
    {
        $haystack = $this->normalize($song->title.' '.$song->author);
        $terms = preg_split('/\s+/', $this->normalize($search), -1, PREG_SPLIT_NO_EMPTY);

        return collect($terms)->every(fn (string $term) => str_contains($haystack, $term));
    }

    // Tri par titre, sans tenir compte de la casse ni des accents : « Ô filii » est rangé avec les O
    private function sortKey(Song $song): array
    {
        return [$this->normalize($song->title), $song->id,];
    }

    private function normalize(string $value): string
    {
        return Str::ascii(Str::lower($value));
    }
}
