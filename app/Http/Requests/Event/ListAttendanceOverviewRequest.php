<?php

namespace App\Http\Requests\Event;

use App\DTOs\Event\ListAttendanceOverviewDTO;
use App\Enums\EventType;
use App\Http\Requests\Concerns\ValidatesPage;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Écran de choix de l'événement (KAN-12) : filtre `type` (sans lui, tous les types) et `page` des événements « Déjà
 * saisis », seul groupe paginé.
 */
class ListAttendanceOverviewRequest extends FormRequest
{
    use ValidatesPage;

    // Défense en profondeur : la route contrôle déjà le rôle (Secrétaire et Admin) avec la Policy
    public function authorize(): bool
    {
        return $this->user()?->can('manageAttendances', Event::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(EventType::class)],
            'page' => $this->pageRules(),
        ];
    }

    public function messages(): array
    {
        $types = implode(', ', array_column(EventType::cases(), 'value'));

        return [
            'type.enum' => 'Le type doit être l\'un de : '.$types.'.',
            ...$this->pageMessages(),
        ];
    }

    public function toDTO(): ListAttendanceOverviewDTO
    {
        $type = $this->input('type');

        return new ListAttendanceOverviewDTO(
            type: filled($type) ? EventType::from($type) : null,
            page: $this->requestedPage(),
        );
    }
}
