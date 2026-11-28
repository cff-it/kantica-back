<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\CreateSongDTO;
use App\Models\Song;
use Illuminate\Foundation\Http\FormRequest;

class CreateSongRequest extends FormRequest
{
    // Second contrôle du rôle (admin, SongPolicy::create), après celui de la route : défense en profondeur si la route
    // perdait son `->can`. Avec la classe, sans charger le cantique
    public function authorize(): bool
    {
        return $this->user()->can('create', Song::class);
    }

    public function rules(): array
    {
        return [
            'title'  => ['required', 'string', 'max:255'],
            'author'    => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:50'],
            'lyrics'    => ['nullable', 'string'],
            'notes'     => ['nullable', 'string'],
        ];
    }

    public function toDTO(): CreateSongDTO
    {
        return new CreateSongDTO(
            title: $this->input('title'),
            author: $this->input('author'),
            reference: $this->input('reference'),
            lyrics: $this->input('lyrics'),
            notes: $this->input('notes'),
        );
    }
}
