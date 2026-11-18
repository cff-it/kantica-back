<?php

namespace App\Actions\User;

use App\DTOs\User\ListUsersImportsDTO;
use App\Models\UserImport;
use App\Repositories\UserImportRepository;
use Illuminate\Pagination\LengthAwarePaginator;

// Historique des imports, du plus récent au plus ancien. L'admin voit tous les imports ; la secrétaire ne voit que les
// siens, comme pour le détail (UserImportPolicy::view) : un rapport contient des noms de membres.
readonly class ListUsersImportsAction
{
    private const int PER_PAGE = 20;

    public function __construct(
        private UserImportRepository $imports,
    ) {}

    /**
     * @return LengthAwarePaginator<int, UserImport>
     */
    public function __invoke(ListUsersImportsDTO $dto): LengthAwarePaginator
    {
        return $this->imports->paginate($dto->viewerIsAdmin ? null : $dto->viewerId, $dto->page, self::PER_PAGE);
    }
}
