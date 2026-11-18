<?php

namespace App\Actions\User;

use App\Enums\SacramentType;
use App\Support\PartialDate;
use Carbon\CarbonImmutable;

// Lit les champs de la fiche de recensement d'une ligne du fichier (adresse, naissance, sacrements, engagement, contact d'urgence,
// pseudo, photo) et les prépare pour les tables `user_profiles`, `user_engagements`, `user_sacraments` et `user_emergency_contacts`.
// Une information illisible n'empêche pas l'import de la ligne : elle est signalée dans `warnings` et non enregistrée.
// Les dates des sacrements, de l'adhésion, de l'engagement et du mariage peuvent n'être connues qu'au mois ou à l'année
// (« 05/1998 », « 2008 ») : elles sont lues en PartialDate. La naissance reste une date complète (pas de précision en base).
readonly class ParseRecensementFieldsAction
{
    private const array DATE_LABELS = [
        'baptism_date'         => 'Date du baptême',
        'first_communion_date' => 'Date de la première communion',
        'confirmation_date'    => 'Date de la confirmation',
        'adhesion_date'        => "Date d'adhésion",
        'engagement_date'      => "Date d'engagement",
        'marriage_date'        => 'Date du mariage',
    ];

    // Sacrements à une date et un lieu (le mariage a en plus le conjoint : il a ses propres champs)
    private const array SACRAMENTS = [
        'baptism'         => SacramentType::Baptism,
        'first_communion' => SacramentType::FirstCommunion,
        'confirmation'    => SacramentType::Confirmation,
    ];

    public function __construct(
        private ExtractPartialDateAction $extractPartialDate,
        private ParseBirthAction $parseBirth,
        private ExtractDriveFileIdAction $extractDriveFileId,
    ) {}

    /**
     * @param  array<string, string>  $values  valeurs brutes de la ligne
     * @return array{
     *     address: string|null,
     *     birth_date: CarbonImmutable|null,
     *     birth_place: string|null,
     *     registration_number: string|null,
     *     picture_url: string|null,
     *     social_pseudo: string|null,
     *     adhesion_date: PartialDate|null,
     *     engagement_date: PartialDate|null,
     *     engagement_place: string|null,
     *     sacraments: array<string, array{date: PartialDate|null, place: string|null}>,
     *     spouse_name: string|null,
     *     spouse_is_member: bool,
     *     marriage_date: PartialDate|null,
     *     marriage_place: string|null,
     *     emergency_name: string|null,
     *     emergency_phone: string|null,
     *     emergency_relationship: string|null,
     *     warnings: list<string>
     * } sacraments : baptême, première communion et confirmation renseignés, indexés par valeur de SacramentType
     */
    public function __invoke(array $values): array
    {
        $warnings = [];
        $birth = ($this->parseBirth)($values['birth']);

        if ($birth['warning'] !== null) {
            $warnings[] = $birth['warning'];
        }

        $dates = [];

        foreach (self::DATE_LABELS as $field => $label) {
            $dates[$field] = ($this->extractPartialDate)($values[$field]);

            if ($values[$field] !== '' && $dates[$field] === null) {
                $warnings[] = $label.' « '.$values[$field].' » non reconnue (jj/mm/aaaa, mm/aaaa ou aaaa attendus) : non enregistrée.';
            }
        }

        $pictureUrl = $this->blankToNull($values['picture_url']);

        // La photo est téléchargée à l'import : seul un lien Google Drive est accepté
        if ($pictureUrl !== null && ($this->extractDriveFileId)($pictureUrl) === null) {
            $warnings[] = 'Photo « '.$pictureUrl.' » : seuls les liens Google Drive sont repris, non enregistrée.';
            $pictureUrl = null;
        }

        $emergencyName = $this->blankToNull($values['emergency_name']);
        $emergencyPhone = $this->blankToNull($values['emergency_phone']);
        $emergencyRelationship = $this->blankToNull($values['emergency_relationship']);

        // Le nom du contact est obligatoire en base : un numéro ou un type sans nom ne peut pas être gardé
        if ($emergencyName === null && $emergencyPhone !== null) {
            $warnings[] = "Numéro du contact d'urgence sans nom : non enregistré.";
            $emergencyPhone = null;
        }

        if ($emergencyName === null && $emergencyRelationship !== null) {
            $warnings[] = "Type de contact d'urgence « ".$emergencyRelationship.' » sans nom : non enregistré.';
            $emergencyRelationship = null;
        }

        $sacraments = [];

        foreach (self::SACRAMENTS as $field => $type) {
            $date = $dates[$field.'_date'];
            $place = $this->blankToNull($values[$field.'_place']);

            // Une ligne `user_sacraments` n'est créée que si le sacrement a une date ou un lieu lisible
            if ($date !== null || $place !== null) {
                $sacraments[$type->value] = ['date' => $date, 'place' => $place,];
            }
        }

        $spouse = $this->blankToNull($values['spouse']);

        return [
            'address'             => $this->blankToNull($values['address']),
            'birth_date'          => $birth['date'],
            'birth_place'         => $birth['place'],
            'registration_number' => $this->blankToNull($values['registration_number']),
            'picture_url'         => $pictureUrl,
            'social_pseudo'       => $this->blankToNull($values['social_pseudo']),
            'adhesion_date'       => $dates['adhesion_date'],
            'engagement_date'     => $dates['engagement_date'],
            'engagement_place'    => $this->blankToNull($values['engagement_place']),
            'sacraments'          => $sacraments,
            'spouse_name'         => $spouse,
            'spouse_is_member'    => $this->isMember($spouse),
            'marriage_date'       => $dates['marriage_date'],
            'marriage_place'      => $this->blankToNull($values['marriage_place']),
            'emergency_name'      => $emergencyName,
            'emergency_phone'     => $emergencyPhone,
            'emergency_relationship' => $emergencyRelationship,
            'warnings'            => $warnings,
        ];
    }

    private function blankToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    // « RABE Exemple, membre de la CFF » ou « RASOA Exemple (CFF) » : conjoint membre ; « non membre » et « pas membre » disent le contraire
    private function isMember(?string $spouse): bool
    {
        return $spouse !== null
            && preg_match('/\b(?:cff|membre)\b/iu', $spouse) === 1
            && preg_match('/\b(?:non|pas)\b/iu', $spouse) !== 1;
    }
}
