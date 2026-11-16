<?php

namespace App\Actions\User;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class DownloadUsersImportTemplateAction
{
    // En-têtes de l'onglet « Réponses » du formulaire de recensement (« Fiche d'identification CFF »), dans leur ordre
    // réel, avec la largeur de chaque colonne. Un export du formulaire se colle donc tel quel dans le modèle.
    // « Horodateur » n'est pas repris par l'import (il apparaît dans `ignored_columns`).
    private const array COLUMNS = [
        'Horodateur'                                                  => 20,
        'Nom et prénom(s)'                                            => 30,
        'Adresse e-mail'                                              => 32,
        'Adresse'                                                     => 32,
        'Numéro de téléphone'                                         => 22,
        'Date et Lieu de naissance'                                   => 30,
        'Voix'                                                        => 12,
        'Pseudo sur Facebook ou Messenger'                            => 32,
        "N° Matricule et Quartier (dans l'Ecar Faravohitra)"          => 46,
        'Date du Baptême'                                             => 18,
        'Lieu du Baptême'                                             => 22,
        'Date de la Première Communion'                               => 30,
        'Lieu de la Première Communion'                               => 30,
        'Date de la Confirmation'                                     => 24,
        'Lieu de la Confirmation'                                     => 24,
        'Marié(e) à...'                                               => 24,
        'Date du Mariage'                                             => 18,
        'Lieu du Mariage'                                             => 22,
        "Date d'adhésion à la chorale"                                => 30,
        "Lieu de l'engagement"                                        => 24,
        "Date d'engagement"                                           => 20,
        "A contacter en cas d'urgence"                                => 30,
        "Nom de la (ou des) personne(s) à contacter en cas d'urgence" => 58,
        "Numéro(s) de téléphone à contacter en cas d'urgence"         => 52,
        "Photo d'identité récente"                                    => 30,
    ];

    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $options = new Options();

                foreach (array_values(self::COLUMNS) as $index => $width) {
                    $options->setColumnWidth($width, $index + 1);
                }

                $writer = new Writer($options);
                $writer->openToFile('php://output');
                $writer->getCurrentSheet()->setName('Réponses');
                $writer->addRow(Row::fromValuesWithStyle(array_keys(self::COLUMNS), new Style(fontBold: true)));
                $writer->close();
            },
            'modele-import-membres.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',],
        );
    }
}
