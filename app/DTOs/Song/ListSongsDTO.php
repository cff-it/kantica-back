<?php

namespace App\DTOs\Song;

class ListSongsDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly int $page,
    ) {}
}
