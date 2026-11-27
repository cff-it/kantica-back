<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\ListSongsDTO;
use Illuminate\Foundation\Http\FormRequest;

class ListSongsRequest extends FormRequest
{
    // L'accès (tout compte validé qui a un rôle) est contrôlé par la Policy sur la route
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100',],
            'page'   => ['nullable', 'integer', 'min:1',],
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'La recherche ne doit pas dépasser 100 caractères.',
            'page.min'   => 'La page doit être supérieure ou égale à 1.',
        ];
    }

    public function toDTO(): ListSongsDTO
    {
        return new ListSongsDTO(
            search: $this->input('search'),
            page: max(1, $this->integer('page', 1)),
        );
    }
}
