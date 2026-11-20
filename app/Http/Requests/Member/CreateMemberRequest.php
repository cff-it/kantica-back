<?php

namespace App\Http\Requests\Member;

use App\DTOs\Member\CreateMemberDTO;
use App\Repositories\UserRepository;
use Closure;

/**
 * Ajout d'un membre par la secrétaire ou l'admin (KAN-89) : l'email du compte et la fiche de recensement complète, avec
 * les mêmes champs et les mêmes validations que la modification de fiche (voir MemberRecordRequest).
 *
 * L'email est l'identifiant de connexion : il est obligatoire ici et ne pourra plus être modifié ensuite. La photo n'est
 * pas gérée.
 */
class CreateMemberRequest extends MemberRecordRequest
{
    // L'email est écrit en minuscules, comme à l'import des membres (KAN-31) : « Hanta@Mail.com » et « hanta@mail.com »
    // sont la même adresse
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('mail'))) {
            $this->merge(['mail' => mb_strtolower(trim($this->input('mail'))),]);
        }
    }

    public function rules(): array
    {
        return [
            'mail' => [
                'bail',
                'required',
                'string',
                'email',
                'max:255',
                // Insensible à la casse : un compte inscrit avec « Hanta@Mail.com » bloque aussi « hanta@mail.com »
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (app(UserRepository::class)->existingMails([$value,]) !== []) {
                        $fail('Cette adresse email est déjà utilisée.');
                    }
                },
            ],
            ...parent::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'mail.required' => 'L\'adresse email est obligatoire.',
            'mail.email'    => 'L\'adresse email n\'est pas valide.',
            'mail.max'      => 'L\'adresse email ne doit pas dépasser 255 caractères.',
        ];
    }

    public function toDTO(): CreateMemberDTO
    {
        return new CreateMemberDTO(
            mail: $this->input('mail'),
            record: $this->record(),
        );
    }
}
