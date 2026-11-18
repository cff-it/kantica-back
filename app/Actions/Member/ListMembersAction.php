<?php

namespace App\Actions\Member;

use App\DTOs\Member\ListMembersDTO;
use App\DTOs\Member\MemberListResultDTO;
use App\Models\User;
use App\Models\VoiceGroup;
use App\Repositories\MemberRepository;
use App\Repositories\VoiceRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

readonly class ListMembersAction
{
    private const int PER_PAGE = 20;

    public function __construct(
        private MemberRepository $members,
        private VoiceRepository $voices,
    ) {}

    /*
     * Recherche, filtre, tri et pagination sont faits en PHP sur l'ensemble des membres (quelques dizaines de
     * choristes) : la recherche insensible aux accents (« Élysée » = « elysee ») est ainsi identique sur
     * PostgreSQL et SQLite, sans extension `unaccent`. À déplacer en SQL si l'effectif dépasse plusieurs milliers.
     */
    public function __invoke(ListMembersDTO $dto): MemberListResultDTO
    {
        $registered = $this->members->allRegistered();

        $matching = $registered
            ->when($dto->voice !== null, fn (Collection $members) => $members->filter(
                fn (User $member) => $this->voiceGroupOf($member) === $dto->voice->value,
            ))
            ->when(filled($dto->search), fn (Collection $members) => $members->filter(
                fn (User $member) => $this->matches($member, $dto->search),
            ))
            ->sortBy(fn (User $member) => $this->sortKey($member))
            ->values();

        $page = new LengthAwarePaginator(
            $matching->forPage($dto->page, self::PER_PAGE)->values(),
            $matching->count(),
            self::PER_PAGE,
            $dto->page,
            ['path' => Paginator::resolveCurrentPath(),],
        );

        return new MemberListResultDTO(
            members: $page->withQueryString(),
            registeredCount: $registered->count(),
            voiceCounts: $this->voiceCounts($registered),
        );
    }

    private function voiceGroupOf(User $member): ?string
    {
        return $member->profile?->voiceGroup?->abbreviation;
    }

    // Tous les termes saisis doivent se retrouver dans le nom ou dans l'un des prénoms (« hanta rasoanaivo » fonctionne)
    private function matches(User $member, string $search): bool
    {
        $haystack = $this->normalize($this->fullName($member));
        $terms = preg_split('/\s+/', $this->normalize($search), -1, PREG_SPLIT_NO_EMPTY);

        return collect($terms)->every(fn (string $term) => str_contains($haystack, $term));
    }

    // Tri par nom puis prénom, sans tenir compte de la casse ni des accents ; les fiches sans nom en dernier
    private function sortKey(User $member): array
    {
        return [
            blank($member->last_name) ? 1 : 0,
            $this->normalize((string) $member->last_name),
            $this->normalize($member->firstnames->pluck('first_name')->implode(' ')),
            $member->id,
        ];
    }

    private function fullName(User $member): string
    {
        return $member->last_name.' '.$member->firstnames->pluck('first_name')->implode(' ');
    }

    private function normalize(string $value): string
    {
        return Str::ascii(Str::lower($value));
    }

    /**
     * @param  Collection<int, User>  $registered
     * @return list<array{voice_group: string, name: string, count: int}>
     */
    private function voiceCounts(Collection $registered): array
    {
        $counts = $registered->countBy(fn (User $member) => $this->voiceGroupOf($member));

        return $this->voices->allGroups()
            ->map(fn (VoiceGroup $group) => [
                'voice_group' => $group->abbreviation,
                'name'        => $group->name,
                'count'       => $counts->get($group->abbreviation, 0),
            ])
            ->all();
    }
}
