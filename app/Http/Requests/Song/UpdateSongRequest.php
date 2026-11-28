<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\UpdateSongDTO;
use App\Repositories\SongRepository;
use Illuminate\Validation\Rule;

class UpdateSongRequest extends SongRequest
{
    // Le cantique est lu ici, avant la validation, volontairement (une lecture par clé primaire) : un cantique inconnu donne
    // un 404 même quand `sections` est invalide, au lieu d'un 422 de la règle `exists` sur des sections qui n'appartiennent
    // à personne. Le rôle est déjà contrôlé avec la classe par la route, donc un non-admin reçoit un 403 avant tout 404.
    public function authorize(SongRepository $songs): bool
    {
        return $this->user()->can('update', $songs->findById((int) $this->route('song')));
    }

    // Une section envoyée avec son identifiant doit appartenir à ce cantique, sans apparaître deux fois
    protected function sectionIdRules(): array
    {
        return [
            'nullable',
            'integer',
            'distinct',
            Rule::exists('song_sections', 'id')->where('song_id', (int) $this->route('song')),
        ];
    }

    public function toDTO(): UpdateSongDTO
    {
        return new UpdateSongDTO(
            id: (int) $this->route('song'),
            title: $this->input('title'),
            author: $this->input('author'),
            reference: $this->input('reference'),
            notes: $this->input('notes'),
            sections: $this->sections(),
        );
    }
}
