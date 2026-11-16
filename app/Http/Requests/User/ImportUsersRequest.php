<?php

namespace App\Http\Requests\User;

use App\DTOs\User\ImportUsersDTO;
use App\DTOs\User\StartUsersImportDTO;
use Illuminate\Foundation\Http\FormRequest;

class ImportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rôles contrôlés par la Policy de la route (`can:import,User`)
        return true;
    }

    public function rules(): array
    {
        return [
            // `zip` : certains outils produisent des .xlsx que la détection de type reconnaît comme une archive zip.
            // Un fichier qui n'est pas un vrai classeur est de toute façon refusé à la lecture.
            'file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx,zip', 'max:5120',],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'   => 'Le fichier est obligatoire.',
            'file.file'       => 'Le fichier n\'a pas pu être téléversé.',
            'file.uploaded'   => 'Le fichier n\'a pas pu être téléversé.',
            'file.extensions' => 'Le fichier doit être au format Excel (.xlsx).',
            'file.mimes'      => 'Le fichier doit être au format Excel (.xlsx).',
            'file.max'        => 'Le fichier ne doit pas dépasser 5 Mo.',
        ];
    }

    public function toDTO(): ImportUsersDTO
    {
        return new ImportUsersDTO(path: $this->file('file')->getRealPath());
    }

    public function toStartDTO(): StartUsersImportDTO
    {
        return new StartUsersImportDTO(
            path: $this->file('file')->getRealPath(),
            userId: $this->user()->id,
        );
    }
}
