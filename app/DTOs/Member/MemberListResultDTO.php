<?php

namespace App\DTOs\Member;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class MemberListResultDTO
{
    /**
     * @param  LengthAwarePaginator<int, User>  $members  page courante, après recherche et filtre de voix
     * @param  int  $registeredCount  tous les membres recensés (« 9 choristes recensés »), hors recherche et filtre
     * @param  list<array{voice_group: string, name: string, count: int}>  $voiceCounts  effectif par groupe de voix
     */
    public function __construct(
        public readonly LengthAwarePaginator $members,
        public readonly int $registeredCount,
        public readonly array $voiceCounts,
    ) {}
}
