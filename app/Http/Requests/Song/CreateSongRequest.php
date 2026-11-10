<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\CreateSongDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
