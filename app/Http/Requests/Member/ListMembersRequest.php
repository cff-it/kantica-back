<?php

namespace App\Http\Requests\Member;

use App\DTOs\Member\ListMembersDTO;
use App\Enums\VoicePart;
use App\Http\Requests\Concerns\ValidatesPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMembersRequest extends FormRequest
{
    use ValidatesPage;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100',],
            'voice'  => ['nullable', Rule::enum(VoicePart::class),],
            'page'   => $this->pageRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'voice.enum' => 'Le groupe de voix doit être S, A, T ou B.',
            ...$this->pageMessages(),
        ];
    }

    public function toDTO(): ListMembersDTO
    {
        return new ListMembersDTO(
            search: $this->input('search'),
            voice: $this->enum('voice', VoicePart::class),
            page: $this->requestedPage(),
        );
    }
}
