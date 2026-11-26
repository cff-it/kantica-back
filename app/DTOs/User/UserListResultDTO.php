<?php

namespace App\DTOs\User;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserListResultDTO
{
    /**
     * @param  LengthAwarePaginator<int, User>  $users  page courante, après recherche
     * @param  array<string, int>  $roleCounts  nombre de comptes par rôle (admin, secretaire, membre), hors recherche
     */
    public function __construct(
        public readonly LengthAwarePaginator $users,
        public readonly array $roleCounts,
    ) {}
}
