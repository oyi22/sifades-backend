<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetStyler
{
    public function header(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('2E7D32');
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    public function body(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public function highlightRed(Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getFont()->getColor()->setRGB('D32F2F');
        $sheet->getStyle($cell)->getFont()->setBold(true);
    }

    public function highlightOrange(Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getFont()->getColor()->setRGB('F57C00');
        $sheet->getStyle($cell)->getFont()->setBold(true);
    }

    public function autoSizeColumns(Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}