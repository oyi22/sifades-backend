<?php

namespace App\Services;

use App\Services\Export\CsvLaporanExporter;
use App\Services\Export\LaporanAbsensiAggregator;
use App\Services\Export\XlsxLaporanExporter;

class ExportService
{
    public function __construct(
        private LaporanAbsensiAggregator $aggregator = new LaporanAbsensiAggregator(),
        private CsvLaporanExporter $csvExporter = new CsvLaporanExporter(),
        private XlsxLaporanExporter $xlsxExporter = new XlsxLaporanExporter(),
    ) {
    }

    public function getDataLaporan(int $tahun, ?int $bulan = null, ?string $tanggal = null, ?int $userId = null): array
    {
        return $this->aggregator->build($tahun, $bulan, $tanggal, $userId);
    }

    public function exportCsv(int $tahun, ?int $bulan = null, ?string $tanggal = null, ?int $userId = null): string
    {
        return $this->csvExporter->export(
            $this->getDataLaporan($tahun, $bulan, $tanggal, $userId),
            $tahun, $bulan, $tanggal, $userId
        );
    }

    public function exportXlsx(int $tahun, ?int $bulan = null, ?string $tanggal = null, ?int $userId = null): string
    {
        return $this->xlsxExporter->export(
            $this->getDataLaporan($tahun, $bulan, $tanggal, $userId),
            $tahun, $bulan, $tanggal, $userId
        );
    }
}