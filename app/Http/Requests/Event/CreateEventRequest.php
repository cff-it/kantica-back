<?php

namespace App\Http\Requests\Event;

use App\DTOs\Event\CreateEventDTO;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'date_start'     => ['required', 'date'],
            'date_end'       => ['nullable', 'date', 'after:date_start'],
            'need_payment'   => ['boolean'],
            'payment_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function toDTO(): CreateEventDTO
    {
        return new CreateEventDTO(
            name: $this->input('name'),
            dateStart: Carbon::parse($this->input('date_start')),
            needPayment: $this->boolean('need_payment'),
            dateEnd: $this->input('date_end') ? Carbon::parse($this->input('date_end')) : null,
            paymentAmount: $this->input('payment_amount'),
        );
    }
}
