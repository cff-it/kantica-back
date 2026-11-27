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
            // Borne haute : au-delà de PHP_INT_MAX / 20, le décalage de la page n'est plus un entier (erreur 500)
            'page'   => ['nullable', 'integer', 'min:1', 'max:100000',],
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'La recherche ne doit pas dépasser 100 caractères.',
            'page.min'   => 'La page doit être supérieure ou égale à 1.',
            'page.max'   => 'La page demandée est trop grande.',
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
