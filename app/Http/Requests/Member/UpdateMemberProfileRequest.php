<?php

namespace App\Http\Requests\Member;

use App\DTOs\Member\MemberEmergencyContactDTO;
use App\DTOs\Member\MemberMarriageDTO;
use App\DTOs\Member\MemberRecordDTO;
use App\DTOs\Member\MemberSacramentDTO;
use App\Rules\PartialDateRule;
use App\Support\PartialDate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Modification de la fiche de recensement complète d'un membre par la secrétaire ou l'admin (US-08, écran
 * « Modifier la fiche »).
 *
 * Le PUT est complet : les champs obligatoires de la maquette le sont ici, et un champ facultatif absent ou vide est
 * effacé (mariage, engagement, pseudo, matricule). Seule exception, la voix précise (S1, A2...) que la maquette ne
 * propose pas : sans clé `voice`, elle reste inchangée tant que le groupe de voix ne change pas.
 *
 * Les dates des sacrements, de l'adhésion, de l'engagement et du mariage peuvent être données au jour, au mois ou à
 * l'année seulement (voir PartialDate). La date de naissance est complète.
 *
 * L'email est celui du compte (identifiant de connexion) : il n'est pas modifiable ici. La photo non plus.
 */
class UpdateMemberProfileRequest extends FormRequest
{
    // Sacrements obligatoires : clé du payload => [libellé du bloc, libellé de la date, libellé du lieu]
    private const array SACRAMENTS = [
        'baptism'         => ['Le baptême', 'La date du baptême', 'Le lieu du baptême',],
        'first_communion' => ['La première communion', 'La date de la première communion', 'Le lieu de la première communion',],
        'confirmation'    => ['La confirmation', 'La date de la confirmation', 'Le lieu de la confirmation',],
    ];

    // Longueur de la colonne `user_emergency_contacts.relationship`, où les liens sont joints par une virgule
    private const int RELATIONSHIPS_MAX_LENGTH = 100;

    public function authorize(): bool
    {
        // Contrôlé en amont par MemberProfilePolicy (middleware `can` de la route)
        return true;
    }

    public function rules(): array
    {
        return [
            'last_name'   => ['required', 'string', 'max:100',],
            'first_name'  => ['required', 'string', 'max:100',],
            'voice_group' => ['required', 'string', Rule::exists('voice_groups', 'abbreviation'),],
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

            'birth_date'                 => ['required', new PartialDateRule('La date de naissance', dayRequired: true),],
            'birth_place'                => ['required', 'string', 'max:255',],
            'address'                    => ['required', 'string', 'max:2000',],
            'social_pseudo'              => ['nullable', 'string', 'max:255',],
            // Obligatoire sur la maquette, mais « à laisser vide si vous résidez à l'étranger » : facultatif ici
            'region_registration_number' => ['nullable', 'string', 'max:255',],

            'adhesion_date'    => ['required', new PartialDateRule('La date d\'adhésion à la chorale'),],
            'engagement_date'  => ['nullable', new PartialDateRule('La date d\'engagement'),],
            'engagement_place' => ['nullable', 'string', 'max:255',],

            ...$this->sacramentRules(),

            'marriage'        => ['nullable', 'array',],
            'marriage.spouse' => ['nullable', 'string', 'max:255',],
            'marriage.date'   => ['nullable', new PartialDateRule('La date du mariage'),],
            'marriage.place'  => ['nullable', 'string', 'max:255',],

            'emergency_contact'                 => ['required', 'array',],
            'emergency_contact.relationships'   => [
                'required',
                'array',
                'min:1',
                'max:10',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && mb_strlen(implode(', ', $value)) > self::RELATIONSHIPS_MAX_LENGTH) {
                        $fail('Les liens avec le contact en cas d\'urgence sont trop longs.');
                    }
                },
            ],
            'emergency_contact.relationships.*' => ['string', 'max:50', 'not_regex:/,/',],
            'emergency_contact.name'            => ['required', 'string', 'max:255',],
            'emergency_contact.phone'           => ['required', 'string', 'max:50',],
        ];
    }

    public function messages(): array
    {
        // Champ => libellé : sert aux messages « est obligatoire » et « ne doit pas dépasser :max caractères »
        $labels = [
            'birth_date'                      => 'La date de naissance',
            'birth_place'                     => 'Le lieu de naissance',
            'address'                         => 'L\'adresse',
            'adhesion_date'                   => 'La date d\'adhésion à la chorale',
            'emergency_contact'               => 'Le contact en cas d\'urgence',
            'emergency_contact.relationships' => 'Le lien avec le contact en cas d\'urgence',
            'emergency_contact.name'          => 'Le nom de la personne à contacter',
            'emergency_contact.phone'         => 'Le numéro de téléphone à contacter',
        ];

        foreach (self::SACRAMENTS as $key => [$blockLabel, $dateLabel, $placeLabel]) {
            $labels[$key] = $blockLabel;
            $labels[$key.'.date'] = $dateLabel;
            $labels[$key.'.place'] = $placeLabel;
        }

        $messages = [
            'last_name.required'                          => 'Le nom est obligatoire.',
            'first_name.required'                         => 'Le prénom est obligatoire.',
            'voice_group.required'                        => 'Le groupe de voix est obligatoire.',
            'voice_group.exists'                          => 'Le groupe de voix doit être S, A, T ou B.',
            'voice.exists'                                => 'Cette voix n\'existe pas dans le groupe de voix choisi.',
            'phone.required'                              => 'Le téléphone est obligatoire.',
            'emergency_contact.relationships.min'         => 'Choisissez au moins un lien avec le contact en cas d\'urgence.',
            'emergency_contact.relationships.*.not_regex' => 'Un lien avec le contact ne peut pas contenir de virgule.',
        ];

        foreach ($labels as $field => $label) {
            $messages[$field.'.required'] = $label.' est obligatoire.';
            $messages[$field.'.max'] = $label.' ne doit pas dépasser :max caractères.';
        }

        return $messages;
    }

    public function toDTO(): MemberRecordDTO
    {
        return new MemberRecordDTO(
            lastName: $this->input('last_name'),
            firstName: $this->input('first_name'),
            voiceGroup: $this->input('voice_group'),
            voice: $this->input('voice'),
            voiceProvided: $this->has('voice'),
            phone: $this->input('phone'),
            birthDate: $this->requiredDate('birth_date'),
            birthPlace: $this->input('birth_place'),
            address: $this->input('address'),
            socialPseudo: $this->input('social_pseudo'),
            registrationNumber: $this->input('region_registration_number'),
            adhesionDate: $this->requiredDate('adhesion_date'),
            engagementDate: $this->optionalDate('engagement_date'),
            engagementPlace: $this->input('engagement_place'),
            baptism: $this->sacrament('baptism'),
            firstCommunion: $this->sacrament('first_communion'),
            confirmation: $this->sacrament('confirmation'),
            marriage: $this->marriage(),
            emergencyContact: $this->emergencyContact(),
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function sacramentRules(): array
    {
        $rules = [];

        foreach (self::SACRAMENTS as $key => [, $dateLabel]) {
            $rules[$key] = ['required', 'array',];
            $rules[$key.'.date'] = ['required', new PartialDateRule($dateLabel),];
            $rules[$key.'.place'] = ['required', 'string', 'max:255',];
        }

        return $rules;
    }

    private function sacrament(string $key): MemberSacramentDTO
    {
        return new MemberSacramentDTO(
            date: $this->requiredDate($key.'.date'),
            place: $this->input($key.'.place'),
        );
    }

    // Sans conjoint, date ni lieu, il n'y a pas de mariage : la ligne existante est supprimée
    private function marriage(): ?MemberMarriageDTO
    {
        $spouse = $this->input('marriage.spouse');
        $date = $this->optionalDate('marriage.date');
        $place = $this->input('marriage.place');

        if ($spouse === null && $date === null && $place === null) {
            return null;
        }

        return new MemberMarriageDTO(spouse: $spouse, date: $date, place: $place);
    }

    private function emergencyContact(): MemberEmergencyContactDTO
    {
        return new MemberEmergencyContactDTO(
            relationships: array_values(array_unique($this->input('emergency_contact.relationships'))),
            name: $this->input('emergency_contact.name'),
            phone: $this->input('emergency_contact.phone'),
        );
    }

    private function optionalDate(string $key): ?PartialDate
    {
        $value = $this->input($key);

        return $value === null ? null : PartialDate::tryParse((string) $value);
    }

    // Les dates obligatoires sont déjà validées : une date illisible ici est une erreur de code
    private function requiredDate(string $key): PartialDate
    {
        return $this->optionalDate($key) ?? throw new LogicException('La date `'.$key.'` aurait dû être validée.');
    }
}
