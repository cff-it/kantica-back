<?php

namespace App\Http\Requests\Mass;

use App\DTOs\Mass\AssignSongToMassPartDTO;
use Illuminate\Foundation\Http\FormRequest;

class AssignSongToMassPartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'song_id'        => ['required', 'integer', 'exists:songs,id'],
            'specific_title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDTO(): AssignSongToMassPartDTO
    {
        return new AssignSongToMassPartDTO(
            massPartId: (int) $this->route('part'),
            songId: $this->integer('song_id'),
            specificTitle: $this->input('specific_title'),
        );
    }
}
