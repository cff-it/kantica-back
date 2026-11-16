<?php

namespace App\Http\Requests\Payment;

use App\DTOs\Payment\CreateEventPaymentDTO;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class CreateEventPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount'  => ['required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'notes'   => ['nullable', 'string'],
        ];
    }

    public function toDTO(): CreateEventPaymentDTO
    {
        return new CreateEventPaymentDTO(
            eventId: (int) $this->route('event'),
            userId: $this->integer('user_id'),
            amount: (float) $this->input('amount'),
            paidAt: $this->input('paid_at') ? Carbon::parse($this->input('paid_at')) : now(),
            notes: $this->input('notes'),
        );
    }
}
