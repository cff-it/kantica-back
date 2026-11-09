<?php

namespace App\Http\Requests\Payment;

use App\DTOs\Payment\RecordUserPaymentDTO;
use Illuminate\Foundation\Http\FormRequest;

class RecordUserPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'           => ['required', 'integer', 'exists:users,id'],
            'event_payment_id'  => ['required', 'integer', 'exists:event_payments,id'],
            'amount_due'        => ['required', 'integer', 'min:0'],
            'paid_amount'       => ['nullable', 'integer', 'min:0'],
            'comment'           => ['nullable', 'string'],
        ];
    }

    public function toDTO(): RecordUserPaymentDTO
    {
        return new RecordUserPaymentDTO(
            userId: $this->integer('user_id'),
            eventPaymentId: $this->integer('event_payment_id'),
            amountDue: $this->integer('amount_due'),
            paidAmount: $this->integer('paid_amount', 0),
            comment: $this->input('comment'),
        );
    }
}
