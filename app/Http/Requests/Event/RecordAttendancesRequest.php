<?php

namespace App\Http\Requests\Event;

use App\DTOs\Event\RecordAttendancesDTO;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saisie des présences d'un événement par la secrétaire ou l'admin (US-11) : la liste complète des identifiants
 * présents. Une liste vide est valide (personne n'est présent) ; l'existence des membres est contrôlée par l'Action.
 */
class RecordAttendancesRequest extends FormRequest
{
    // Défense en profondeur : la route contrôle déjà le rôle (Secrétaire et Admin) avec la Policy
    public function authorize(): bool
    {
        return $this->user()?->can('manageAttendances', Event::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // `present` et non `required` : la liste vide (tout décocher) doit passer
            'present_member_ids'   => ['present', 'array', 'max:1000',],
            'present_member_ids.*' => ['integer', 'min:1', 'distinct',],
        ];
    }

    public function messages(): array
    {
        return [
            'present_member_ids.present'    => 'La liste des membres présents est obligatoire.',
            'present_member_ids.array'      => 'La liste des membres présents doit être un tableau.',
            'present_member_ids.max'        => 'La liste ne peut pas contenir plus de 1000 membres.',
            'present_member_ids.*.integer'  => 'Chaque identifiant de membre doit être un nombre entier.',
            'present_member_ids.*.min'      => 'Chaque identifiant de membre doit être supérieur ou égal à 1.',
            'present_member_ids.*.distinct' => 'Un membre ne peut être présent qu\'une fois par événement.',
        ];
    }

    public function toDTO(): RecordAttendancesDTO
    {
        return new RecordAttendancesDTO(
            eventId: (int) $this->route('event'),
            presentMemberIds: array_map('intval', array_values($this->input('present_member_ids'))),
        );
    }
}
