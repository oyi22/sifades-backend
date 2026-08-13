<?php

namespace App\Services\Export;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LaporanAbsensiAggregator
{
    private const HARI_LABEL = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    public function build(int $tahun, ?int $bulan = null, ?string $tanggal = null, ?int $userId = null): array
    {
        $users = $this->getUsersDenganRelasi($tahun, $bulan, $tanggal, $userId);

        $rows  = [];
        $logNotifGagal = [];
        $detailHarian = [];
        $rekapHarianLengkap = [];

        foreach ($users as $index => $user) { 
            $rekapHarian = $this->buildRekapHarianLengkap($user, $tahun, $bulan, $tanggal);

            $rows[] = $this->buildRingkasanUser($user, $index, $rekapHarian);
            $logNotifGagal = array_merge($logNotifGagal, $this->buildLogNotifGagal($user));
            $detailHarian  = array_merge($detailHarian, $this->buildDetailHarian($user));

            if ($userId) {
                $rekapHarianLengkap = $rekapHarian;
            }
        }

        return [
            'tahun'   => $tahun,
            'bulan'   => $bulan,
            'tanggal' => $tanggal,
            'data'    => $rows,
            'log_notif_gagal'      => $logNotifGagal,
            'detail_harian'        => $detailHarian,
            'rekap_harian_lengkap' => $rekapHarianLengkap,
        ];
    }

    private function getUsersDenganRelasi(int $tahun, ?int $bulan, ?string $tanggal, ?int $userId)
    {
        $query = User::with([
            'absensis' => function ($q) use ($tahun, $bulan, $tanggal) {
                if ($tanggal) {
                    $q->whereDate('tanggal', $tanggal);
                } elseif ($bulan) {
                    $q->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
                } else {
                    $q->whereYear('tanggal', $tahun);
                }
            },
            'izins' => function ($q) use ($tahun, $bulan, $tanggal) {
                if ($tanggal) {
                    $q->where('status', 'disetujui')
                    ->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal);
                } elseif ($bulan) {
                    $q->where('status', 'disetujui')
                    ->whereMonth('tanggal_mulai', $bulan)
                    ->whereYear('tanggal_mulai', $tahun);
                } else {
                    $q->where('status', 'disetujui')->whereYear('tanggal_mulai', $tahun);
                }
            },
            'notifikasiLogs' => function ($q) use ($tahun, $bulan, $tanggal) {   // ✅ baru
                if ($tanggal) {
                    $q->whereDate('created_at', $tanggal);
                } elseif ($bulan) {
                    $q->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun);
                } else {
                    $q->whereYear('created_at', $tahun);
                }
            },
        ]);

        if ($userId) {
            $query->where('id', $userId);
        }

        return $query->get();
    }

    private function buildRingkasanUser(User $user, int $index, array $rekapHarian): array
    {
        $hadir = $user->absensis->whereIn('status', ['hadir', 'telat'])->count();
        $telat = $user->absensis->where('status', 'telat')->count();
        $izin  = $user->izins->count();
        $notifGagal = $user->absensis->where('notif_wa_terkirim', false)->count();

        $skor = $this->hitungSkorKehadiran($rekapHarian);

        return [
            'no' => $index + 1,
            'nama' => $user->nama_lengkap,
            'nik'  => $user->nik,
            'jabatan'  => $user->jabatan,
            'alamat' => $user->alamat,
            'total_hadir' => $hadir,
            'total_telat' => $telat,
            'total_izin' => $izin,
            'total_alpha' => $skor['total_alpha'],
            'total_hari_kerja' => $skor['total_hari_kerja'],
            'poin_kehadiran'   => $skor['poin'],
            'notif_gagal' => $notifGagal,
            'per_bulan' => $this->buildRekapPerBulan($user),
        ];
    }

    private function hitungSkorKehadiran(array $rekapHarian): array
    {
        $poin = 0;
        $totalAlpha = 0;

        foreach ($rekapHarian as $r) {
            $poin += match ($r['status']) {
                'Hadir' => 10,
                'Telat' => 7,
                'Izin'  => 5,
                'Alpha' => 0,
                default => 0,
            };

            if ($r['status'] === 'Alpha') {
                $totalAlpha++;
            }
        }

        return [
            'poin' => $poin,
            'total_hari_kerja' => count($rekapHarian),
            'total_alpha' => $totalAlpha,
        ];
    }

    private function buildRekapPerBulan(User $user): array
    {
        $perBulan = [];

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $perBulan[$bulan] = [
                'hadir' => $user->absensis
                    ->whereIn('status', ['hadir', 'telat'])
                    ->filter(fn ($a) => $a->tanggal->month === $bulan)
                    ->count(),
                'izin' => $user->izins
                    ->filter(fn ($i) => $i->tanggal_mulai->month === $bulan)
                    ->count(),
            ];
        }

        return $perBulan;
    }

    private function buildLogNotifGagal(User $user): array
    {
        $log = [];

        foreach ($user->absensis->where('notif_wa_terkirim', false) as $a) {
            $log[] = [
                'nama' => $user->nama_lengkap,
                'no_wa' => $user->no_wa ?? '-',
                'tanggal' => $a->tanggal->format('d-m-Y'),
                'jenis'   => 'Notif Absensi',
            ];
        }

        return $log;
    }

    private function buildDetailHarian(User $user): array
    {
        $detail = [];

        foreach ($user->absensis->sortBy('tanggal') as $a) {
            $detail[] = [
                'nama'  => $user->nama_lengkap,
                'jabatan' => $user->jabatan,
                'tanggal'  => $a->tanggal->format('d-m-Y'),
                'jam_masuk' => $a->jam_masuk ? substr($a->jam_masuk, 0, 5) : '-',
                'status' => ucfirst($a->status),
                'jarak'  => $a->jarak_dari_kantor !== null ? "{$a->jarak_dari_kantor} m" : '-',
            ];
        }

        return $detail;
    }
 
    private function buildRekapHarianLengkap(User $user, int $tahun, ?int $bulan, ?string $tanggal): array
    {
        [$start, $end] = $this->resolveRentangTanggal($tahun, $bulan, $tanggal);

        $absensiByTanggal = $user->absensis->keyBy(fn ($a) => $a->tanggal->format('Y-m-d'));
        $izinList = $user->izins;

        $rekap = [];

        foreach (CarbonPeriod::create($start, $end) as $tgl) {
            $dow = (int) $tgl->format('w');
            if (in_array($dow, [0, 6])) continue;  

            $tglStr  = $tgl->format('Y-m-d');
            $absensi = $absensiByTanggal->get($tglStr);

            if ($absensi) {
                $status    = ucfirst($absensi->status);
                $jamMasuk  = $absensi->jam_masuk ? substr($absensi->jam_masuk, 0, 5) : '-';
                $jamPulang = $absensi->jam_pulang ? substr($absensi->jam_pulang, 0, 5) : '-';
            } else {
                $izin = $izinList->first(fn ($i) => $tgl->between($i->tanggal_mulai, $i->tanggal_selesai));
                $status    = $izin ? 'Izin' : 'Alpha';
                $jamMasuk  = '-';
                $jamPulang = '-';
            }

            $rekap[] = [
                'tanggal'    => $tgl->format('d-m-Y'),
                'hari'       => self::HARI_LABEL[$dow],
                'status'     => $status,
                'jam_masuk'  => $jamMasuk,
                'jam_pulang' => $jamPulang,
            ];
        }

        return $rekap;
    }

    private function resolveRentangTanggal(int $tahun, ?int $bulan, ?string $tanggal): array
    {
        if ($tanggal) {
            $d = Carbon::parse($tanggal);
            return [$d->copy()->startOfDay(), $d->copy()->endOfDay()];
        }
        if ($bulan) {
            $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
            return [$start, $start->copy()->endOfMonth()];
        }
        $start = Carbon::create($tahun, 1, 1)->startOfYear();
        return [$start, $start->copy()->endOfYear()];
    }

    private function buildLogNotifikasi(User $user): array
{
    $log = [];

    foreach ($user->notifikasiLogs->sortBy('created_at') as $n) {
        $log[] = [
            'nama'    => $user->nama_lengkap,
            'no_wa'   => $user->no_wa ?? '-',
            'tanggal' => $n->created_at->format('d-m-Y'),
            'jam'     => $n->dikirim_pada ? $n->dikirim_pada->format('H:i:s') : $n->created_at->format('H:i:s'),
            'jenis'   => $this->labelJenisNotif($n->tipe),
            'status'  => $n->terkirim ? 'Terkirim' : 'Gagal',
        ];
    }

    return $log;
}

    private function labelJenisNotif(string $tipe): string
    {
        return match ($tipe) {
            'absensi'   => 'Notifikasi Absensi',
            'pengajuan' => 'Notifikasi Pengajuan Izin',
            'disetujui' => 'Notifikasi Izin Disetujui',
            'ditolak'   => 'Notifikasi Izin Ditolak',
            default     => ucfirst($tipe),
        };
    }
}