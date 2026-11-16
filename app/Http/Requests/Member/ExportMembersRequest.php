<?php

namespace App\Http\Requests\Member;

use App\DTOs\Member\ExportMembersDTO;
use App\Enums\ExportFormat;
use App\Enums\VoicePart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportMembersRequest extends FormRequest
{
    // L'accès (Secrétaire et Admin) est contrôlé par la Policy sur la route
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(ExportFormat::class),],
            'voice'  => ['nullable', Rule::enum(VoicePart::class),],
        ];
    }

    public function messages(): array
    {
        return [
            'format.required' => 'Le format d\'export est obligatoire.',
            'format.enum'     => 'Le format d\'export doit être csv, xlsx ou pdf.',
            'voice.enum'      => 'La voix doit être S, A, T ou B.',
        ];
    }

    public function toDTO(): ExportMembersDTO
    {
        return new ExportMembersDTO(
            format: ExportFormat::from($this->input('format')),
            voice: $this->enum('voice', VoicePart::class),
        );
    }
}
