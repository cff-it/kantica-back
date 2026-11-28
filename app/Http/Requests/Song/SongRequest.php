<?php

namespace App\Http\Requests\Song;

use App\DTOs\Song\SongSectionDTO;
use App\Enums\SectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Champs et validations communs à l'ajout (CreateSongRequest) et à la modification (UpdateSongRequest) d'un cantique,
 * avec la liste ordonnée de ses sections : `sections: [{id?, type: "couplet"|"refrain"|"pont", number?, text}]`.
 *
 * L'ordre de la liste est l'ordre des sections. Le numéro (Couplet 1, 2, 3) est saisi, facultatif et sans contrôle d'unicité.
 * Les sections ne sont pas obligatoires : un cantique peut être ajouté avant d'avoir ses paroles (point ouvert de KAN-38 :
 * au moins une section ?). À la modification, `sections` absent ou `null` laisse les sections inchangées, `[]` les supprime
 * toutes (sauf celles qu'une messe utilise : 422).
 */
abstract class SongRequest extends FormRequest
{
    // Bornes larges pour un cantique : un couplet ou une note tient en quelques lignes
    private const SECTIONS_MAX_COUNT = 100;

    private const SECTION_TEXT_MAX_LENGTH = 5000;

    private const NOTES_MAX_LENGTH = 2000;

    public function rules(): array
    {
        return [
            'title'     => ['required', 'string', 'max:255',],
            'author'    => ['nullable', 'string', 'max:255',],
            'reference' => ['nullable', 'string', 'max:50',],
            'notes'     => ['nullable', 'string', 'max:'.self::NOTES_MAX_LENGTH,],

            // Chaque section est une écriture en base (et une règle `exists` à la modification) : la liste est bornée
            'sections'          => ['nullable', 'array', 'max:'.self::SECTIONS_MAX_COUNT,],
            'sections.*.id'     => $this->sectionIdRules(),
            'sections.*.type'   => ['required', Rule::enum(SectionType::class),],
            'sections.*.number' => ['nullable', 'integer', 'min:1', 'max:999',],
            'sections.*.text'   => ['required', 'string', 'max:'.self::SECTION_TEXT_MAX_LENGTH,],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.max'                => 'Les notes ne peuvent pas dépasser :max caractères.',
            'sections.array'           => 'Les sections doivent être envoyées sous forme de liste.',
            'sections.max'             => 'Un cantique ne peut pas avoir plus de :max sections.',
            'sections.*.text.max'      => 'Le texte d\'une section ne peut pas dépasser :max caractères.',
            'sections.*.id.integer'    => 'L\'identifiant de la section est invalide.',
            'sections.*.id.distinct'   => 'Une section ne peut pas apparaître deux fois dans la liste.',
            'sections.*.id.exists'     => 'Cette section n\'appartient pas à ce cantique.',
            'sections.*.id.prohibited' => 'Un nouveau cantique ne peut pas reprendre une section existante.',
            'sections.*.type.required' => 'Le type de la section est obligatoire.',
            'sections.*.type.enum'     => 'Le type de la section doit être "couplet", "refrain" ou "pont".',
            'sections.*.number.integer' => 'Le numéro de la section doit être un nombre entier.',
            'sections.*.number.min'    => 'Le numéro de la section doit être au moins :min.',
            'sections.*.number.max'    => 'Le numéro de la section ne peut pas dépasser :max.',
            'sections.*.text.required' => 'Le texte de la section est obligatoire.',
        ];
    }

    /**
     * Règles de `sections.*.id` : l'identifiant d'une section déjà enregistrée, qui ne peut venir que du cantique modifié.
     *
     * @return list<mixed>
     */
    abstract protected function sectionIdRules(): array;

    /**
     * @return list<SongSectionDTO>|null null quand la requête ne contient pas de liste de sections
     */
    protected function sections(): ?array
    {
        // `input` et non `validated` : validated() regroupe les sections par règle (celles avec `id` d'abord) et
        // casserait l'ordre de la liste, qui est l'ordre des sections
        $sections = $this->input('sections');

        if ($sections === null) {
            return null;
        }

        return array_map(
            fn (array $section) => new SongSectionDTO(
                id: isset($section['id']) ? (int) $section['id'] : null,
                type: SectionType::from($section['type']),
                number: isset($section['number']) ? (int) $section['number'] : null,
                text: $section['text'],
            ),
            array_values($sections),
        );
    }
}
