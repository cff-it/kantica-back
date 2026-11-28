<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\CreateSongDTO;
use App\Models\Song;

class CreateSongRequest extends SongRequest
{
    // Second contrôle du rôle (admin, SongPolicy::create), après celui de la route : défense en profondeur si la route
    // perdait son `->can`. Avec la classe, sans charger le cantique
    public function authorize(): bool
    {
        return $this->user()->can('create', Song::class);
    }

    // Un cantique qui n'existe pas encore n'a pas de section à reprendre
    protected function sectionIdRules(): array
    {
        return ['prohibited',];
    }

    public function toDTO(): CreateSongDTO
    {
        return new CreateSongDTO(
            title: $this->input('title'),
            author: $this->input('author'),
            reference: $this->input('reference'),
            notes: $this->input('notes'),
            sections: $this->sections() ?? [],
        );
    }
}
