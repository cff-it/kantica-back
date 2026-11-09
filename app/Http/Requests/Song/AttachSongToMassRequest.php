<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\AttachSongToMassDTO;
use Illuminate\Foundation\Http\FormRequest;

class AttachSongToMassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mass_part_id' => ['required', 'integer', 'exists:mass_parts,id'],
            'order'        => ['required', 'integer', 'min:0'],
        ];
    }

    public function toDTO(): AttachSongToMassDTO
    {
        return new AttachSongToMassDTO(
            songId: (int) $this->route('song'),
            massPartId: $this->integer('mass_part_id'),
            order: $this->integer('order'),
        );
    }
}
