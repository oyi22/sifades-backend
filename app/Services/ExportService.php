<?php 
namespace App\Services;

use App\Models\Absensi;
use App\Models\User;
use Illuminate\Support\Collection;

class ExportService
{
    public function getDataLaporanTahunan(int $tahun): array
    {
        $users = User::with(['absensis' => function ($q) use ($tahun) {
            $q->whereYear('tanggal', $tahun);
        }, 'izins' => function ($q) use ($tahun) {
            $q->whereYear('tanggal', $tahun)->where('status', 'disetujui');
        }])->get();

        $rows = [];

        foreach ($users as $index => $user) {
            $hadir = $user->absensis->where('status', 'hadir')->count();
            $izin  = $user->izins->count();
 
            $perBulan = [];
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $hadirBulan = $user->absensis
                    ->where('status', 'hadir')
                    ->filter(fn($a) => $a->tanggal->month === $bulan)
                    ->count();
                $izinBulan = $user->izins
                    ->filter(fn($i) => $i->tanggal->month === $bulan)->count();

                $perBulan[$bulan] = [
                    'hadir' => $hadirBulan,
                    'izin' => $izinBulan,
                ];
            }

            $rows[] = [
                'no' => $index + 1,
                'nama' => $user->nama_lengkap,
                'nik' => $user->nik,
                'jabatan' => $user->jabatan,
                'alamat' => $user->alamat,
                'total_hadir' => $hadir,
                'total_izin' => $izin,
                'per_bulan' => $perBulan,
            ];
        }

        return [
            'tahun' => $tahun,
            'data' => $rows,
        ];
    }

    public function exportCsv(int $tahun): string
    {
        $laporan = $this->getDataLaporanTahunan($tahun);
        $bulanLabel = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        $header = ['No', 'Nama', 'NIK', 'Jabatan', 'Alamat'];
        foreach ($bulanLabel as $b) {
            $header[] = "Hadir-{$b}";
            $header[] = "Izin-{$b}";
        }
        $header[] = 'Total Hadir';
        $header[] = 'Total Izin';

        $rows = [$header];
        foreach ($laporan['data'] as $row) {
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

        $filename = "laporan_absensi_{$tahun}.csv";
        $path     = storage_path("app/exports/{$filename}");

        if (!is_dir(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        $fp = fopen($path, 'w');
        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }
        fclose($fp);

        return $path;
    }
}