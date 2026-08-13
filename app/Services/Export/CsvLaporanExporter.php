<?php

namespace App\Services\Export;

class CsvLaporanExporter
{
    private const BULAN_LABEL = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    public function export(array $laporan, int $tahun, ?int $userId = null): string
    {
        $rows = array_merge(
            [$this->buildHeader()],
            $this->buildRows($laporan['data'])
        );

        $path = $this->resolvePath($tahun, $userId, $laporan['data']);
        $this->writeCsv($path, $rows);

        return $path;
    }

    private function buildHeader(): array
    {
        $header = ['No', 'Nama', 'NIK', 'Jabatan', 'Alamat'];

        foreach (self::BULAN_LABEL as $b) {
            $header[] = "Hadir-{$b}";
            $header[] = "Izin-{$b}";
        }

        $header[] = 'Total Hadir';
        $header[] = 'Total Izin';

        return $header;
    }

    private function buildRows(array $data): array
    {
        $rows = [];

        foreach ($data as $row) {
            $line = [
                $row['no'],
                $row['nama'],
                $row['nik'],
                $row['jabatan'],
                $row['alamat'],
            ];

            for ($b = 1; $b <= 12; $b++) {
                $line[] = $row['per_bulan'][$b]['hadir'];
                $line[] = $row['per_bulan'][$b]['izin'];
            }

            $line[] = $row['total_hadir'];
            $line[] = $row['total_izin'];

            $rows[] = $line;
        }

        return $rows;
    }

    private function resolvePath(int $tahun, ?int $userId, array $data): string
    {
        $dir = storage_path('app/exports');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($userId && !empty($data[0]['nama'])){
            $namaSlug = \Illuminate\Support\Str::slug($data[0]['nama']);
            return "{$dir}/laporan_absensi_{$namaSlug}_{$tahun}.csv";
        }

        return "{$dir}/laporan_absensi_{$tahun}.csv";
    }

    private function writeCsv(string $path, array $rows): void
    {
        $fp = fopen($path, 'w');
        fwrite($fp, "\xEF\xBB\xBF");
        fwrite($fp, "sep=,\n");

        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }

        fclose($fp);
    }
}