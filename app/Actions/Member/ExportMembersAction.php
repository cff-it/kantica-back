<?php

namespace App\Actions\Member;

use App\DTOs\Member\ExportMembersDTO;
use App\Enums\ExportFormat;
use App\Enums\VoicePart;
use App\Models\User;
use App\Repositories\UserRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ExportMembersAction
{
    private const array COLUMNS = ['Nom', 'Prénoms', 'Voix', 'Voix précise', 'Téléphone', 'Email',];

    // Largeur des colonnes du fichier Excel, dans l'ordre de COLUMNS
    private const array XLSX_COLUMN_WIDTHS = [24, 28, 8, 14, 20, 38,];

    public function __construct(
        private UserRepository $users,
    ) {}

    public function __invoke(ExportMembersDTO $dto): Response
    {
        $rows = $this->users->members($dto->voice)
            ->map(fn (User $member): array => $this->row($member))
            ->all();
        $filename = 'membres-cff-'.now()->format('Y-m-d').'.'.$dto->format->value;

        return match ($dto->format) {
            ExportFormat::Csv => $this->csv($rows, $filename),
            ExportFormat::Xlsx => $this->xlsx($rows, $filename),
            ExportFormat::Pdf => $this->pdf($rows, $dto->voice, $filename),
        };
    }

    /**
     * @return list<string>
     */
    private function row(User $member): array
    {
        return [
            $member->last_name ?? '',
            $member->firstnames->pluck('first_name')->implode(' '),
            $member->profile?->voiceGroup?->abbreviation ?? '',
            $member->profile?->voice?->name ?? '',
            $member->profile?->phone ?? '',
            $member->mail,
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($rows): void {
                $handle = fopen('php://output', 'w');

                // BOM UTF-8 : Excel détecte l'encodage et affiche correctement les accents
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, self::COLUMNS, ';', escape: '', eol: "\r\n");

                foreach ($rows as $row) {
                    fputcsv($handle, array_map($this->neutralizeFormula(...), $row), ';', escape: '', eol: "\r\n");
                }

                fclose($handle);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8',],
        );
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($rows): void {
                $options = new Options();

                foreach (self::XLSX_COLUMN_WIDTHS as $index => $width) {
                    $options->setColumnWidth($width, $index + 1);
                }

                $writer = new Writer($options);
                $writer->openToFile('php://output');
                $writer->getCurrentSheet()->setName('Membres');
                $writer->addRow(Row::fromValuesWithStyle(self::COLUMNS, new Style(fontBold: true)));

                foreach ($rows as $row) {
                    // Cellules explicitement textuelles : Row::fromValues() transformerait une valeur « =... » en formule
                    $writer->addRow(new Row(array_map(
                        fn (string $value): Cell => $value === '' ? new EmptyCell(null) : new StringCell($value),
                        $row,
                    )));
                }

                $writer->close();
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',],
        );
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function pdf(array $rows, ?VoicePart $voice, string $filename): Response
    {
        return Pdf::loadView('exports.members', [
            'columns'    => self::COLUMNS,
            'rows'       => $rows,
            'scope'      => $this->scope(count($rows), $voice),
            'exportedAt' => now(),
        ])
            // Sans sous-ensemble de police, DomPDF embarque DejaVu en entier (environ 900 Ko par PDF, contre 30 Ko)
            ->setOption(['enable_font_subsetting' => true,])
            ->download($filename);
    }

    // Rappel du périmètre exporté, comme dans le panneau « Exporter la liste » (« 9 choristes · toutes les voix »)
    private function scope(int $count, ?VoicePart $voice): string
    {
        return $count.' '.($count > 1 ? 'choristes' : 'choriste').' · '.($voice === null ? 'toutes les voix' : 'voix '.$voice->value);
    }

    // Les champs sont saisis par les utilisateurs : un tableur ne doit jamais les interpréter comme une formule
    private function neutralizeFormula(string $value): string
    {
        $startsLikeFormula = preg_match('/^[=@\t\r]/', $value) === 1;

        // « + » et « - » en tête ne sont acceptés que pour un numéro de téléphone (+261 34 00 000 00)
        $startsLikeSignedFormula = preg_match('/^[+-]/', $value) === 1
            && preg_match('/^[+-]?[\d\s().-]+$/', $value) !== 1;

        return $startsLikeFormula || $startsLikeSignedFormula ? "'".$value : $value;
    }
}
