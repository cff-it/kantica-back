<?php

namespace App\Http\Requests\Member;

use App\DTOs\Member\AssignVoiceDTO;
use App\Enums\VoicePart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Attribution de la voix d'un membre par la secrétaire ou l'admin (US-10, feuille « Attribuer la voix ») : seul le
 * groupe de voix (S, A, T, B) est envoyé, le reste de la fiche n'est pas demandé.
 */
class AssignVoiceRequest extends FormRequest
{
    // L'accès (Secrétaire et Admin) est contrôlé par la Policy sur la route
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voice' => ['required', Rule::enum(VoicePart::class),],
        ];
    }

    public function messages(): array
    {
        return [
            'voice.required' => 'La voix est obligatoire.',
            'voice.enum'     => 'La voix doit être S, A, T ou B.',
        ];
    }

    public function toDTO(): AssignVoiceDTO
    {
        return new AssignVoiceDTO(
            memberId: (int) $this->route('member'),
            voice: VoicePart::from($this->input('voice')),
        );
    }
}
