<?php

namespace App\Http\Requests\Song;

use App\Models\Song;
use Illuminate\Foundation\Http\FormRequest;

// Suppression d'un cantique : second contrôle du rôle (admin, SongPolicy::delete), après celui de la route. Défense en
// profondeur si la route perdait son `->can` ; avec la classe, sans charger le cantique (DeleteSongAction le lit une fois)
class DeleteSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', Song::class);
    }

    public function rules(): array
    {
        return [];
    }
}
