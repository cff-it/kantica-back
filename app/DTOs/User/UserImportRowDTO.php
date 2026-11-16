<?php

namespace App\DTOs\User;

use Carbon\CarbonImmutable;

// Ligne valide du fichier : `line` est le numéro de la ligne dans Excel (l'en-tête est la ligne 1)
class UserImportRowDTO
{
    /**
     * @param  array<string, array{date: CarbonImmutable|null, place: string|null}>  $sacraments  baptême, première communion et confirmation renseignés, indexés par valeur de SacramentType
     * @param  list<string>  $warnings  informations de la ligne qui n'ont pas pu être lues : le compte est créé sans elles
     */
    public function __construct(
        public readonly int $line,
        public readonly string $lastName,
        public readonly string $firstName,
        public readonly string $voice,
        public readonly ?string $phone,
        public readonly string $mail,
        public readonly ?string $address = null,
        public readonly ?CarbonImmutable $birthDate = null,
        public readonly ?string $birthPlace = null,
        public readonly ?string $registrationNumber = null,
        public readonly ?string $pictureUrl = null,
        public readonly ?string $socialPseudo = null,
        public readonly ?CarbonImmutable $adhesionDate = null,
        public readonly ?CarbonImmutable $engagementDate = null,
        public readonly ?string $engagementPlace = null,
        public readonly array $sacraments = [],
        public readonly ?string $spouseName = null,
        public readonly bool $spouseIsMember = false,
        public readonly ?CarbonImmutable $marriageDate = null,
        public readonly ?string $marriagePlace = null,
        public readonly ?string $emergencyName = null,
        public readonly ?string $emergencyPhone = null,
        public readonly ?string $emergencyRelationship = null,
        public readonly array $warnings = [],
    ) {}

    public function displayName(): string
    {
        return $this->lastName.' '.$this->firstName;
    }

    public function hasEngagement(): bool
    {
        return $this->adhesionDate !== null || $this->engagementDate !== null || $this->engagementPlace !== null;
    }

    public function hasMarriage(): bool
    {
        return $this->spouseName !== null || $this->marriageDate !== null || $this->marriagePlace !== null;
    }

    /**
     * @return list<UserImportIssueDTO>
     */
    public function warningIssues(): array
    {
        return array_map(
            fn (string $message): UserImportIssueDTO => new UserImportIssueDTO($this->line, $this->displayName(), $message),
            $this->warnings,
        );
    }
}
