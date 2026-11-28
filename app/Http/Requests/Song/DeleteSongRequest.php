<?php

namespace App\Http\Requests\Song;

use App\Models\Song;
use App\Repositories\SongRepository;
use Illuminate\Foundation\Http\FormRequest;

// Suppression d'un cantique : réservée à l'admin (SongPolicy::delete). Sans ce contrôle, tout compte validé pouvait
// supprimer un cantique. Comme pour la modification (KAN-38), le cantique est chargé avant la Policy : un cantique
// inconnu donne un 404 à tous les rôles, sans rien révéler puisque la liste est lisible par tous (voir DeleteSongTest)
class DeleteSongRequest extends FormRequest
{
    private Song $songToDelete;

    public function authorize(SongRepository $songs): bool
    {
        $this->songToDelete = $songs->findById((int) $this->route('song'));

        return $this->user()->can('delete', $this->songToDelete);
    }

    public function rules(): array
    {
        return [];
    }

    // Le cantique chargé pour la Policy, transmis tel quel à DeleteSongAction
    public function song(): Song
    {
        return $this->songToDelete;
    }
}
