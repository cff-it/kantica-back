<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\ListSongsDTO;
use App\Http\Requests\Concerns\ValidatesPage;
use Illuminate\Foundation\Http\FormRequest;

class ListSongsRequest extends FormRequest
{
    use ValidatesPage;

    // L'accès (tout compte validé qui a un rôle) est contrôlé par la Policy sur la route
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100',],
            'page'   => $this->pageRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'La recherche ne doit pas dépasser 100 caractères.',
            ...$this->pageMessages(),
        ];
    }

    public function toDTO(): ListSongsDTO
    {
        return new ListSongsDTO(
            search: $this->input('search'),
            page: $this->requestedPage(),
        );
    }
}
