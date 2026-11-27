<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Une ligne du répertoire (GET /api/songs, US-04) : ce que la liste affiche, rien de plus. Le cantique complet (notes,
// sections, usage) reste servi par SongResource
class SongListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'author'    => $this->author,
            'reference' => $this->reference,
        ];
    }
}
