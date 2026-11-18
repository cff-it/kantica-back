<?php

namespace App\Http\Requests\Member;

use App\DTOs\Member\MemberProfileDTO;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Groupe de voix obligatoire uniquement si le membre peut encore le modifier (voir UserPolicy)
        $voiceGroupRequirement = $this->user()->can('updateOwnVoice', User::class) ? 'required' : 'nullable';

        return [
            'last_name'   => ['required', 'string', 'max:100',],
            'first_name'  => ['required', 'string', 'max:100',],
            'voice_group' => [$voiceGroupRequirement, 'string', Rule::exists('voice_groups', 'abbreviation'),],
            // Voix précise facultative (S1, S2, ...), qui doit appartenir au groupe choisi
            'voice'       => [
                'nullable',
                'string',
                Rule::exists('voices', 'name')->where(fn ($query) => $query->whereIn(
                    'vocal_group_id',
                    fn ($groups) => $groups->select('id')
                        ->from('voice_groups')
                        ->where('abbreviation', $this->input('voice_group')),
                )),
            ],
            'phone'       => ['required', 'string', 'max:30',],
        ];
    }

    public function messages(): array
    {
        return [
            'last_name.required'   => 'Le nom est obligatoire.',
            'first_name.required'  => 'Le prénom est obligatoire.',
            'voice_group.required' => 'Le groupe de voix est obligatoire.',
            'voice_group.exists'   => 'Le groupe de voix doit être S, A, T ou B.',
            'voice.exists'         => 'Cette voix n\'existe pas dans le groupe de voix choisi.',
            'phone.required'       => 'Le téléphone est obligatoire.',
        ];
    }

    public function toDTO(): MemberProfileDTO
    {
        return new MemberProfileDTO(
            lastName: $this->input('last_name'),
            firstName: $this->input('first_name'),
            voiceGroup: $this->input('voice_group'),
            voice: $this->input('voice'),
            phone: $this->input('phone'),
        );
    }
}
