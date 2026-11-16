<?php

namespace App\Actions\User;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class DownloadUsersImportTemplateAction
{
    // Mêmes colonnes que l'écran d'import ; la parenthèse de « Voix » rappelle les valeurs acceptées
    private const array COLUMNS = ['Nom', 'Prénom', 'Voix (S, A, T ou B)', 'Téléphone', 'Email',];

    // Largeur des colonnes, dans l'ordre de COLUMNS
    private const array COLUMN_WIDTHS = [24, 24, 22, 20, 38,];

    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $options = new Options();

                foreach (self::COLUMN_WIDTHS as $index => $width) {
                    $options->setColumnWidth($width, $index + 1);
                }

                $writer = new Writer($options);
                $writer->openToFile('php://output');
                $writer->getCurrentSheet()->setName('Membres');
                $writer->addRow(Row::fromValuesWithStyle(self::COLUMNS, new Style(fontBold: true)));
                $writer->close();
            },
            'modele-import-membres.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',],
        );
    }
}
