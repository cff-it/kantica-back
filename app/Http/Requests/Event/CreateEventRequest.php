<?php

namespace App\Http\Requests\Event;

use App\DTOs\Event\CreateEventDTO;
use App\Enums\EventType;
use App\Models\Event;
use App\Support\ChoirTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulaire « Nouvel événement » (KAN-12) : type, intitulé (prérempli par le front), date et heure, lieu facultatif.
 * Une date sans décalage se lit dans le fuseau de la chorale (voir ChoirTime) ; `date_end`, `need_payment` et
 * `payment_amount` restent facultatifs (module Paiements).
 */
class CreateEventRequest extends FormRequest
{
    // Défense en profondeur : la route contrôle déjà le rôle (Secrétaire et Admin) avec la Policy
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type'           => ['required', Rule::enum(EventType::class)],
            'name'           => ['required', 'string', 'max:255'],
            'date_start'     => ['required', 'date'],
            'date_end'       => ['nullable', 'date', 'after:date_start'],
            'location'       => ['nullable', 'string', 'max:255'],
            'need_payment'   => ['boolean'],
            'payment_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        $types = implode(', ', array_column(EventType::cases(), 'value'));

        return [
            'type.required'       => 'Le type de l\'événement est obligatoire.',
            'type.enum'           => 'Le type doit être l\'un de : '.$types.'.',
            'name.required'       => 'L\'intitulé de l\'événement est obligatoire.',
            'name.max'            => 'L\'intitulé ne doit pas dépasser 255 caractères.',
            'date_start.required' => 'La date et l\'heure de l\'événement sont obligatoires.',
            'date_start.date'     => 'La date et l\'heure de l\'événement ne sont pas valides.',
            'date_end.date'       => 'La date de fin de l\'événement n\'est pas valide.',
            'date_end.after'      => 'La fin de l\'événement doit être après son début.',
            'location.max'        => 'Le lieu ne doit pas dépasser 255 caractères.',
        ];
    }

    public function toDTO(): CreateEventDTO
    {
        return new CreateEventDTO(
            name: $this->input('name'),
            type: EventType::from($this->input('type')),
            dateStart: ChoirTime::parse($this->input('date_start')),
            location: $this->input('location'),
            needPayment: $this->boolean('need_payment'),
            dateEnd: $this->input('date_end') ? ChoirTime::parse($this->input('date_end')) : null,
            paymentAmount: $this->input('payment_amount'),
        );
    }
}
