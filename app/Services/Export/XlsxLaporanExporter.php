<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class XlsxLaporanExporter
{
    private const BULAN_LABEL = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    public function __construct(
        private SpreadsheetStyler $styler = new SpreadsheetStyler()
    ) {
    }

    public function export(array $laporan, int $tahun, ?int $bulan = null, ?string $tanggal = null, ?int $userId = null): string
    {
        $spreadsheet = new Spreadsheet();

        // 1. Sheet Rekap Tahunan
        $this->buildSheetRekapTahunan($spreadsheet->getActiveSheet(), $laporan['data']);
        
        // 2. Sheet Rekap Bulanan
        $this->buildSheetRekapBulanan($spreadsheet->createSheet(), $laporan['data']);
        
        // 3. Sheet Log Notif Gagal
        $this->buildSheetLogNotifGagal($spreadsheet->createSheet(), $laporan['log_notif_gagal'] ?? []);
        
        // 4. Sheet Detail Kehadiran
        $this->buildSheetDetailKehadiran($spreadsheet->createSheet(), $laporan['detail_harian'] ?? []);

        // 5. Sheet Rekap Harian Lengkap (Optional)
        if (!empty($laporan['rekap_harian_lengkap'])) {
            $this->buildSheetRekapHarianLengkap($spreadsheet->createSheet(), $laporan['rekap_harian_lengkap']);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $path = $this->resolvePath($tahun, $bulan, $tanggal, $userId, $laporan['data']);
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function buildSheetRekapTahunan(Worksheet $sheet, array $data): void
    {
        $sheet->setTitle('Rekap Tahunan');

        $header = ['No', 'Nama', 'NIK', 'Jabatan', 'Alamat', 'Total Hadir', 'Total Izin', '% Kehadiran', 'Notif Gagal'];
        $sheet->fromArray($header, null, 'A1', true);
        $this->styler->header($sheet, 'A1:I1');

        $rowIdx = 2;
        foreach ($data as $row) {
            $persen = $this->hitungPersenKehadiran($row);

            $sheet->setCellValue("A{$rowIdx}", $row['no']);
            $sheet->setCellValue("B{$rowIdx}", $row['nama']); 
            $sheet->setCellValueExplicit("C{$rowIdx}", (string) $row['nik'], DataType::TYPE_STRING);
            $sheet->setCellValue("D{$rowIdx}", $row['jabatan']);
            $sheet->setCellValue("E{$rowIdx}", $row['alamat']);
            $sheet->setCellValue("F{$rowIdx}", $row['total_hadir']);
            $sheet->setCellValue("G{$rowIdx}", $row['total_izin']);
            $sheet->setCellValue("H{$rowIdx}", "{$persen}%");
            $sheet->setCellValue("I{$rowIdx}", $row['notif_gagal']);

            if (($row['notif_gagal'] ?? 0) > 0) {
                $this->styler->highlightRed($sheet, "I{$rowIdx}");
            }

            $rowIdx++;
        }

        if ($rowIdx > 2) {
            $this->styler->body($sheet, "A2:I" . ($rowIdx - 1));
        }
        $this->styler->autoSizeColumns($sheet, range('A', 'I'));
    }

    private function buildSheetRekapHarianLengkap(Worksheet $sheet, array $rekap): void
    {
        $sheet->setTitle('Rekap Harian');

        $sheet->fromArray(['Tanggal', 'Hari', 'Status', 'Jam Masuk', 'Jam Pulang'], null, 'A1', true);
        $this->styler->header($sheet, 'A1:E1');

        $rowIdx = 2;
        foreach ($rekap as $r) {
            $sheet->fromArray([
                $r['tanggal'], $r['hari'], $r['status'], $r['jam_masuk'], $r['jam_pulang'],
            ], null, "A{$rowIdx}", true);

            if ($r['status'] === 'Alpha') {
                $this->styler->highlightRed($sheet, "C{$rowIdx}");
            } elseif ($r['status'] === 'Telat') {
                $this->styler->highlightOrange($sheet, "C{$rowIdx}");
            }

            $rowIdx++;
        }

        if ($rowIdx > 2) {
            $this->styler->body($sheet, "A2:E" . ($rowIdx - 1));
        }
        $this->styler->autoSizeColumns($sheet, range('A', 'E'));
    }

    private function hitungPersenKehadiran(array $row): float
    {
        $totalHariKerja = $row['total_hari_kerja'] ?? 0;

        if ($totalHariKerja <= 0) {
            return 0;
        }

        $poinMaksimal = $totalHariKerja * 10;
        $poinDidapat  = $row['poin_kehadiran'] ?? 0;

        return round(($poinDidapat / $poinMaksimal) * 100, 1);
    }
    private function buildSheetRekapBulanan(Worksheet $sheet, array $data): void
    {
        $sheet->setTitle('Rekap Bulanan');

        $sheet->fromArray(['No', 'Nama', 'NIK', 'Jabatan', 'Alamat'], null, 'A1', true);
        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->mergeCells("{$col}1:{$col}2");
        }

        $lastCol = $this->buildBulanHeader($sheet);

        $this->styler->header($sheet, "A1:{$lastCol}2");
        $sheet->getStyle("A1:{$lastCol}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowIdx = 3;
        foreach ($data as $row) {
            $sheet->setCellValue("A{$rowIdx}", $row['no']);
            $sheet->setCellValue("B{$rowIdx}", $row['nama']);
            $sheet->setCellValueExplicit("C{$rowIdx}", (string) $row['nik'], DataType::TYPE_STRING);
            $sheet->setCellValue("D{$rowIdx}", $row['jabatan']);
            $sheet->setCellValue("E{$rowIdx}", $row['alamat']);

            $col = 6;
            for ($b = 1; $b <= 12; $b++) {
                $colHadir = Coordinate::stringFromColumnIndex($col);
                $colIzin  = Coordinate::stringFromColumnIndex($col + 1);

                $sheet->setCellValue("{$colHadir}{$rowIdx}", $row['per_bulan'][$b]['hadir'] ?? 0);
                $sheet->setCellValue("{$colIzin}{$rowIdx}", $row['per_bulan'][$b]['izin'] ?? 0);

                $col += 2;
            }

            $rowIdx++;
        }

        if ($rowIdx > 3) {
            $this->styler->body($sheet, "A3:{$lastCol}" . ($rowIdx - 1));
        } 
        $allCols = [];
        $startIdx = Coordinate::columnIndexFromString('A');
        $endIdx   = Coordinate::columnIndexFromString($lastCol);

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            $allCols[] = Coordinate::stringFromColumnIndex($i);
        }

        $this->styler->autoSizeColumns($sheet, $allCols);
    }

    private function buildBulanHeader(Worksheet $sheet): string
    {
        $col = 6;

        foreach (self::BULAN_LABEL as $b) {
            $colLetter  = Coordinate::stringFromColumnIndex($col);
            $colLetter2 = Coordinate::stringFromColumnIndex($col + 1);

            $sheet->setCellValue("{$colLetter}1", $b);
            $sheet->mergeCells("{$colLetter}1:{$colLetter2}1");
            $sheet->setCellValue("{$colLetter}2", 'Hadir');
            $sheet->setCellValue("{$colLetter2}2", 'Izin');

            $col += 2;
        }

        return Coordinate::stringFromColumnIndex($col - 1);
    }

    private function buildSheetLogNotifGagal(Worksheet $sheet, array $log): void
    {
        $sheet->setTitle('Log Notif Gagal');

        $sheet->fromArray(['Nama', 'No WA', 'Tanggal', 'Jenis Notif'], null, 'A1', true);
        $this->styler->header($sheet, 'A1:D1');

        if (empty($log)) {
            $sheet->setCellValue('A2', 'Tidak ada notifikasi gagal');
            $sheet->mergeCells('A2:D2');
        } else {
            $rowIdx = 2;
            foreach ($log as $item) {
                $sheet->setCellValue("A{$rowIdx}", $item['nama']);
                $sheet->setCellValueExplicit("B{$rowIdx}", (string) $item['no_wa'], DataType::TYPE_STRING);
                $sheet->setCellValue("C{$rowIdx}", $item['tanggal']);
                $sheet->setCellValue("D{$rowIdx}", $item['jenis']);

                $rowIdx++;
            }
            $this->styler->body($sheet, "A2:D" . ($rowIdx - 1));
        }

        $this->styler->autoSizeColumns($sheet, range('A', 'D'));
    }

    private function buildSheetDetailKehadiran(Worksheet $sheet, array $detail): void
    {
        $sheet->setTitle('Detail Kehadiran');

        $sheet->fromArray(['Nama', 'Jabatan', 'Tanggal', 'Jam Masuk', 'Status'], null, 'A1', true);
        $this->styler->header($sheet, 'A1:E1');

        if (empty($detail)) {
            $sheet->setCellValue('A2', 'Belum ada data presensi');
            $sheet->mergeCells('A2:E2');
        } else {
            $rowIdx = 2;
            foreach ($detail as $d) {
                $sheet->fromArray([
                    $d['nama'], $d['jabatan'], $d['tanggal'], $d['jam_masuk'], $d['status'],
                ], null, "A{$rowIdx}", true);
 
                if ($d['jam_masuk'] !== '-' && !empty($d['jam_masuk'])) {
                    if (strtotime($d['jam_masuk']) > strtotime('08:00:00')) {
                        $this->styler->highlightOrange($sheet, "D{$rowIdx}");
                    }
                }

                $rowIdx++;
            }
            $this->styler->body($sheet, "A2:E" . ($rowIdx - 1));
        }

        $this->styler->autoSizeColumns($sheet, range('A', 'E'));
    }

    private function resolvePath(int $tahun, ?int $bulan, ?string $tanggal, ?int $userId, array $data): string
    {
        $dir = storage_path('app/exports');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $periodeSlug = $tanggal ? str_replace('-', '', $tanggal) : ($bulan ? "{$tahun}_" . str_pad((string)$bulan, 2, '0', STR_PAD_LEFT) : "{$tahun}");
        $namaSlug    = ($userId && !empty($data[0]['nama'])) ? \Illuminate\Support\Str::slug($data[0]['nama']) . '_' : '';

        return "{$dir}/laporan_absensi_{$namaSlug}{$periodeSlug}.xlsx";
    }
}