<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConfirmationReportExport implements FromCollection, WithEvents
{
    protected Collection $rows;
    protected string $reportTitle;
    protected string $companyName;
    protected string $period;
    protected Carbon $generatedAt;

    /**
     * @param iterable $rows
     */
    public function __construct(iterable $rows, string $reportTitle, string $companyName, string $period, Carbon $generatedAt)
    {
        $this->rows = collect($rows);
        $this->reportTitle = $reportTitle;
        $this->companyName = $companyName;
        $this->period = $period;
        $this->generatedAt = $generatedAt;
    }

    public function collection(): Collection
    {
        // Ensure placeholders for empty values
        return $this->rows->map(function ($row) {
            return [
                $row['hfm_account'] ?? '—',
                $row['trading_partner'] ?? '—',
                $row['current_amount'] ?? 0,
                $row['counterparty_amount'] ?? 0,
                $row['variance'] ?? 0,
                $row['agreement'] ?? '—',
                $row['prepared_by'] ?? '—',
                $row['prepared_at'] ?? '—',
                $row['reviewed_by'] ?? '—',
                $row['reviewed_at'] ?? '—',
                $row['counter_prepared_by'] ?? '—',
                $row['counter_prepared_at'] ?? '—',
                $row['counter_reviewed_by'] ?? '—',
                $row['counter_reviewed_at'] ?? '—',
            ];
        });
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                // Insert meta rows at the top
                $sheet->insertNewRowBefore(1, 8); // make room for meta + headers

                // Row 1: report title
                $sheet->setCellValue('A1', $this->reportTitle);
                $sheet->mergeCells('A1:N1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Row 2: company
                $sheet->setCellValue('A2', 'Company: ' . $this->companyName);
                $sheet->getStyle('A2')->getFont()->setBold(true);

                // Row 3: period
                $sheet->setCellValue('A3', 'Period: ' . $this->period);
                $sheet->getStyle('A3')->getFont()->setBold(true);

                // Row 4: generated
                $sheet->setCellValue('A4', 'Generated: ' . $this->generatedAt->format('Y-m-d H:i'));
                $sheet->getStyle('A4')->getFont()->setBold(true);

                // Row 5: spacer (empty)

                // Header rows: row 7 (group headers), row 8 (subheaders)
                $headerRow1 = 7;
                $headerRow2 = 8;
                $dataStartRow = 9;

                // Group headers
                $sheet->mergeCells("G{$headerRow1}:J{$headerRow1}");
                $sheet->mergeCells("K{$headerRow1}:N{$headerRow1}");
                $sheet->setCellValue("G{$headerRow1}", 'CURRENT COMPANY');
                $sheet->setCellValue("K{$headerRow1}", 'COUNTER-PART COMPANY');
                $sheet->getStyle("G{$headerRow1}:J{$headerRow1}")->applyFromArray($this->groupHeaderStyle('#00A1D6'));
                $sheet->getStyle("K{$headerRow1}:N{$headerRow1}")->applyFromArray($this->groupHeaderStyle('#008A00'));

                // Subheaders
                $subHeaders = [
                    'HFM ACCOUNT', 'TRADING PARTNER', 'CURRENT COMPANY AMOUNT', 'COUNTERPARTY AMOUNT',
                    'VARIANCE', 'AGREEMENT',
                    'PREPARED BY', 'PREPARED AT', 'REVIEWED BY', 'REVIEWED AT',
                    'PREPARED BY', 'PREPARED AT', 'REVIEWED BY', 'REVIEWED AT',
                ];
                $sheet->fromArray($subHeaders, null, "A{$headerRow2}");

                $sheet->getStyle("A{$headerRow2}:J{$headerRow2}")->applyFromArray($this->subHeaderStyle('#E6F2FF'));
                $sheet->getStyle("K{$headerRow2}:N{$headerRow2}")->applyFromArray($this->subHeaderStyle('#E5F4EA'));

                // Apply borders to header rows
                $sheet->getStyle("A{$headerRow1}:N{$headerRow2}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFCCCCCC'],
                        ],
                    ],
                ]);

                // Column widths (approximate to screenshot)
                $widths = [
                    'A' => 22,
                    'B' => 28,
                    'C' => 22,
                    'D' => 22,
                    'E' => 18,
                    'F' => 14,
                    'G' => 14,
                    'H' => 18,
                    'I' => 14,
                    'J' => 18,
                    'K' => 14,
                    'L' => 18,
                    'M' => 14,
                    'N' => 18,
                ];
                foreach ($widths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // Accounting format for amounts (R currency, parentheses for negatives)
                $accountingFormat = '_-"R" * #,##0.00_ ;-"R" * (#,##0.00);_-"R" * "-"??_ ;_(@_)';
                $lastDataRow = $dataStartRow + $this->rows->count() - 1;
                if ($lastDataRow >= $dataStartRow) {
                    $sheet->getStyle("C{$dataStartRow}:E{$lastDataRow}")
                        ->getNumberFormat()
                        ->setFormatCode($accountingFormat);
                }

                // Red font for variance when != 0
                if ($lastDataRow >= $dataStartRow) {
                    for ($r = $dataStartRow; $r <= $lastDataRow; $r++) {
                        $varianceCell = "E{$r}";
                        if ($sheet->getCell($varianceCell)->getValue() != 0) {
                            $sheet->getStyle($varianceCell)->getFont()->getColor()->setARGB(Color::COLOR_RED);
                        }
                    }
                }

                // Row shading: Agree rows green, otherwise zebra with light blue
                $agreeFill = [
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFEBF7E6'],
                    ],
                ];
                $zebraFill = [
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF5FBFF'],
                    ],
                ];
                if ($lastDataRow >= $dataStartRow) {
                    for ($r = $dataStartRow; $r <= $lastDataRow; $r++) {
                        $agreement = $sheet->getCell("F{$r}")->getValue();
                        if (strtolower((string) $agreement) === 'agree') {
                            $sheet->getStyle("A{$r}:N{$r}")->applyFromArray($agreeFill);
                        } elseif (($r - $dataStartRow) % 2 === 0) {
                            $sheet->getStyle("A{$r}:N{$r}")->applyFromArray($zebraFill);
                        }
                    }
                }

                // Alignments
                $sheet->getStyle("A{$dataStartRow}:N{$lastDataRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Borders for data
                if ($lastDataRow >= $dataStartRow) {
                    $sheet->getStyle("A{$dataStartRow}:N{$lastDataRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFEEEEEE'],
                            ],
                        ],
                    ]);
                }
            },
        ];
    }

    protected function groupHeaderStyle(string $hex): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => $this->hexToArgb($hex)],
            ],
        ];
    }

    protected function subHeaderStyle(string $hex): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FF000000'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => $this->hexToArgb($hex)],
            ],
        ];
    }

    protected function hexToArgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 6) {
            return 'FF' . strtoupper($hex);
        }
        return strtoupper($hex);
    }
}
