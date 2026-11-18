<?php

namespace App\Rules;

use App\Enums\DatePrecision;
use App\Support\PartialDate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Date de la fiche de recensement : jour, mois ou année seulement (voir PartialDate), jamais dans le futur.
 * `$dayRequired` impose une date complète (date de naissance).
 */
readonly class PartialDateRule implements ValidationRule
{
    public function __construct(
        private string $label,
        private bool $dayRequired = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Une année seule peut arriver comme nombre JSON (2008) : elle est lue comme « 2008 »
        $date = is_string($value) || is_int($value) ? PartialDate::tryParse((string) $value) : null;

        if ($date === null || ($this->dayRequired && $date->precision !== DatePrecision::Day)) {
            $formats = $this->dayRequired ? 'jj/mm/aaaa' : 'jj/mm/aaaa, mm/aaaa ou aaaa';
            $fail($this->label.' n\'est pas une date valide ('.$formats.').');

            return;
        }

        if ($date->isFuture()) {
            $fail($this->label.' ne peut pas être dans le futur.');
        }
    }
}
