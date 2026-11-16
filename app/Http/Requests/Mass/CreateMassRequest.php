<?php

namespace App\Http\Requests\Mass;

use App\DTOs\Mass\CreateMassDTO;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class CreateMassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'             => ['required', 'date'],
            'liturgical_name'  => ['nullable', 'string', 'max:255'],
            'prayer_cycle'     => ['nullable', 'string', 'in:A,B,C'],
            'notes'            => ['nullable', 'string'],
            'is_public'        => ['boolean'],
        ];
    }

    public function toDTO(): CreateMassDTO
    {
        return new CreateMassDTO(
            date: Carbon::parse($this->input('date')),
            liturgicalName: $this->input('liturgical_name'),
            prayerCycle: $this->input('prayer_cycle'),
            notes: $this->input('notes'),
            isPublic: $this->boolean('is_public', true),
        );
    }
}
