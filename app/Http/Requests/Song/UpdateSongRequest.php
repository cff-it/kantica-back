<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\UpdateSongDTO;
use App\Repositories\SongRepository;
use Illuminate\Validation\Rule;

class UpdateSongRequest extends SongRequest
{
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
