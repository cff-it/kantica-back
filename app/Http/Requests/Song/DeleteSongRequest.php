<?php

namespace App\Http\Requests\Song;

use App\Repositories\SongRepository;
use Illuminate\Foundation\Http\FormRequest;

// Suppression d'un cantique : réservée à l'admin (SongPolicy::delete). Sans ce contrôle, tout compte validé pouvait
// supprimer un cantique. Comme pour la modification (KAN-38), un cantique inconnu donne un 404
class DeleteSongRequest extends FormRequest
{
    public function authorize(SongRepository $songs): bool
    {
        return $this->user()->can('delete', $songs->findById((int) $this->route('song')));
    }

    public function rules(): array
    {
        return [];
    }
}
