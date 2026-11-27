<?php

namespace App\Actions\User;

use App\DTOs\User\ListUsersDTO;
use App\DTOs\User\UserListResultDTO;
use App\Enums\RoleName;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

readonly class ListUsersWithRolesAction
{
    private const int PER_PAGE = 20;

    public function __construct(private UserRepository $users) {}

    /*
     * Comme la liste des membres (ListMembersAction) : recherche, tri et pagination en PHP sur l'ensemble des comptes
     * (quelques dizaines), pour une recherche insensible aux accents identique sur PostgreSQL et SQLite. Nombre de
     * requêtes constant (prénoms et rôles chargés d'avance, voir ManageRolesTest). Limite connue : au-delà de quelques
     * milliers de comptes, filtrer et paginer en SQL (extension `unaccent` de PostgreSQL).
     */
    public function __invoke(ListUsersDTO $dto): UserListResultDTO
    {
        $all = $this->users->allWithRoles();

        $matching = $all
            ->when(filled($dto->search), fn (Collection $users) => $users->filter(
                fn (User $user) => $this->matches($user, $dto->search),
            ))
            ->sortBy(fn (User $user) => $this->sortKey($user))
            ->values();

        $page = new LengthAwarePaginator(
            $matching->forPage($dto->page, self::PER_PAGE)->values(),
            $matching->count(),
            self::PER_PAGE,
            $dto->page,
            ['path' => Paginator::resolveCurrentPath(),],
        );

        return new UserListResultDTO(
            users: $page->withQueryString(),
            roleCounts: $this->roleCounts($all),
        );
    }

    // Tous les termes saisis doivent se retrouver dans le nom, les prénoms ou l'email
    private function matches(User $user, string $search): bool
    {
        $haystack = $this->normalize(
            $user->last_name.' '.$user->firstnames->pluck('first_name')->implode(' ').' '.$user->mail,
        );
        $terms = preg_split('/\s+/', $this->normalize($search), -1, PREG_SPLIT_NO_EMPTY);

        return collect($terms)->every(fn (string $term) => str_contains($haystack, $term));
    }

    // Tri par nom puis prénom, sans tenir compte de la casse ni des accents ; les comptes sans nom en dernier
    private function sortKey(User $user): array
    {
        return [
            blank($user->last_name) ? 1 : 0,
            $this->normalize((string) $user->last_name),
            $this->normalize($user->firstnames->pluck('first_name')->implode(' ')),
            $user->id,
        ];
    }

    private function normalize(string $value): string
    {
        return Str::ascii(Str::lower($value));
    }

    /**
     * Décompte des comptes affichés, donc sans le compte administrateur originel (compte technique, masqué de la
     * liste). La garde « dernier admin » d'AssignRoleAction le compte, elle : c'est un vrai admin. Avec ce compte et un
     * seul autre admin, l'écran affiche « 1 admin » et cet admin peut quitter le rôle (voir ManageRolesTest).
     *
     * @param  Collection<int, User>  $users
     * @return array<string, int>
     */
    private function roleCounts(Collection $users): array
    {
        $counts = $users->countBy(fn (User $user) => RoleName::highestOf($user->roles->pluck('name'))?->value);

        return collect(RoleName::cases())
            ->mapWithKeys(fn (RoleName $role) => [$role->value => $counts->get($role->value, 0),])
            ->all();
    }
}
