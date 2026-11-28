<?php

namespace App\DTOs\Event;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

class EventAttendanceListDTO
{
    /**
     * @param  Collection<int, User>  $members  membres actifs, triés par nom puis prénom
     * @param  list<int>  $presentIds  identifiants des membres de la liste présents à l'événement
     */
    public function __construct(
        public readonly Event $event,
        public readonly Collection $members,
        public readonly array $presentIds,
    ) {}
}
