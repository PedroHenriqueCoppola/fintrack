<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportExporter
{
    public function __construct(private ReportFormatter $formatter) {}

    public function download(ReportData $report, string $format): Response
    {
        return $format === 'pdf' ? $this->pdf($report) : $this->excel($report);
    }

    public function filename(ReportData $report, string $extension): string
    {
        $title = Str::slug($report->title) ?: 'relatorio';
        $period = Str::slug($report->periodLabel) ?: 'periodo';

        return "fintrack-{$title}-{$period}.{$extension}";
    }

    private function pdf(ReportData $report): Response
    {
        $pdf = Pdf::loadView('reports.show', [
            'report' => $report,
            'formatter' => $this->formatter,
        ]);
        $pdf->setPaper('a4', $report->landscape ? 'landscape' : 'portrait');
        $pdf->setOption('default_font', 'DejaVu Sans');
        $pdf->setOption('enable_php', true);
        $pdf->setOption('enable_remote', false);
        $pdf->setOption('font_dir', base_path('vendor/dompdf/dompdf/lib/fonts'));
        $pdf->setOption('font_cache', sys_get_temp_dir());

        return $pdf->download($this->filename($report, 'pdf'));
    }

    private function excel(ReportData $report): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'fintrack-xlsx-');

        if ($path === false) {
            throw new \RuntimeException('Não foi possível criar o arquivo temporário.');
        }

        $writer = new Writer;
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName($report->title));

        $bold = (new Style)->withFontBold(true);
        $headerRow = 0;
        $widths = [];
        $rowNumber = 0;

        $this->writeLine($writer, [$report->title], $bold, $widths, $rowNumber);
        $this->writeLine($writer, ['Período: '.$report->periodLabel], null, $widths, $rowNumber);
        $this->writeLine($writer, ['Filtros: '.$report->filtersSummary], null, $widths, $rowNumber);

        if ($report->note !== null) {
            $this->writeLine($writer, [$report->note], null, $widths, $rowNumber);
        }

        $this->writeLine($writer, [''], null, $widths, $rowNumber);

        foreach ($report->sections as $index => $section) {
            if ($index > 0) {
                $this->writeLine($writer, [''], null, $widths, $rowNumber);
            }

            if (! empty($section['title'])) {
                $this->writeLine($writer, [$section['title']], $bold, $widths, $rowNumber);
            }

            $labels = array_map(fn (array $column): string => $column['label'], $section['columns']);
            $this->writeLine($writer, $labels, $bold, $widths, $rowNumber);

            if ($headerRow === 0) {
                $headerRow = $rowNumber;
            }

            foreach ($section['rows'] as $row) {
                $this->writeDataRow($writer, $section['columns'], $row, false, $widths, $rowNumber);
            }

            if (! empty($section['totals'])) {
                $this->writeDataRow($writer, $section['columns'], $section['totals'], true, $widths, $rowNumber);
            }
        }

        foreach ($widths as $column => $width) {
            $sheet->setColumnWidth((float) $width, $column);
        }

        if ($headerRow > 0) {
            $sheet->setSheetView((new SheetView)->withFreezeRow($headerRow + 1));
        }

        $writer->close();

        return response()
            ->download($path, $this->filename($report, 'xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, int>  $widths
     */
    private function writeLine(Writer $writer, array $values, ?Style $style, array &$widths, int &$rowNumber): void
    {
        $cells = [];

        foreach ($values as $index => $value) {
            $cells[] = Cell::fromValue($value, $style);
            $this->widen($widths, $index + 1, (string) $value);
        }

        $writer->addRow(new Row($cells));
        $rowNumber++;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $widths
     */
    private function writeDataRow(Writer $writer, array $columns, array $row, bool $bold, array &$widths, int &$rowNumber): void
    {
        $cells = [];

        foreach ($columns as $index => $column) {
            $value = $row[$column['key']] ?? null;
            [$cellValue, $style, $widthText] = $this->excelCell($value, $column, $bold);
            $cells[] = Cell::fromValue($cellValue, $style);
            $this->widen($widths, $index + 1, $widthText);
        }

        $writer->addRow(new Row($cells));
        $rowNumber++;
    }

    /**
     * The float cast only stores the already calculated decimal in the cell.
     * Totals are not computed here.
     *
     * @param  array<string, mixed>  $column
     * @return array{0: mixed, 1: Style, 2: string}
     */
    private function excelCell(mixed $value, array $column, bool $bold): array
    {
        $style = $bold ? (new Style)->withFontBold(true) : new Style;
        $missing = $value === null || $value === '';

        if ($missing && $column['type'] !== 'integer') {
            return ['—', $style, '—'];
        }

        return match ($column['type']) {
            'money' => [
                $this->excelNumber((string) $value),
                $style->withFormat('R$ #,##0.00;-R$ #,##0.00')->withCellAlignment(CellAlignment::RIGHT),
                'R$ 000.000,00',
            ],
            'percent' => [
                $this->excelNumber((string) $value),
                $style->withFormat('0.0%;-0.0%')->withCellAlignment(CellAlignment::RIGHT),
                '000,0%',
            ],
            'date' => [
                new DateTimeImmutable($value.' 12:00:00', new DateTimeZone('UTC')),
                $style->withFormat('dd/mm/yyyy')->withCellAlignment(CellAlignment::RIGHT),
                '00/00/0000',
            ],
            'integer' => [
                (int) $value,
                $style->withFormat('0')->withCellAlignment(CellAlignment::RIGHT),
                (string) (int) $value,
            ],
            default => [$value, $style, (string) $value],
        };
    }

    private function excelNumber(string $amount): float
    {
        return (float) $amount;
    }

    /**
     * @param  array<int, int>  $widths
     */
    private function widen(array &$widths, int $column, string $text): void
    {
        $width = min(42, max(12, mb_strlen($text) + 2));
        $widths[$column] = max($widths[$column] ?? 0, $width);
    }

    private function sheetName(string $title): string
    {
        $name = str_replace(['\\', '/', '?', '*', ':', '[', ']'], ' ', $title);
        $name = trim($name, " \t\n\r\0\x0B'");

        if ($name === '' || strcasecmp($name, 'History') === 0) {
            $name = 'Relatorio';
        }

        if (mb_strlen($name) > 31) {
            $name = rtrim(mb_substr($name, 0, 31));
        }

        return $name;
    }
}
