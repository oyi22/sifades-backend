<?php
// app/Services/RiwayatService.php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\NotifikasiLog;
use App\Models\RiwayatAktifitas;
use App\Models\RiwayatAktivitas;
use Carbon\Carbon;

class RiwayatService
{
    
    public function catatAbsensi(int $userId, Absensi $absensi): void
    {
        RiwayatAktivitas::create([
            'user_id'        => $userId,
            'tipe'           => 'absensi',
            'judul'          => 'Absensi ' . ucfirst($absensi->status),
            'deskripsi'      => "Check-in pukul {$absensi->jam_masuk}" . ($absensi->alamat_lokasi ? " — {$absensi->alamat_lokasi}" : ''),
            'status'         => $absensi->status,
            'referensi_tipe' => 'absensi',
            'referensi_id'   => $absensi->id,
            'meta'           => [
                'jam_masuk'         => $absensi->jam_masuk,
                'alamat_lokasi'     => $absensi->alamat_lokasi,
                'jarak_dari_kantor' => $absensi->jarak_dari_kantor,
                'skor_kepercayaan'  => $absensi->skor_kepercayaan,
            ],
            'terjadi_pada'   => Carbon::parse("{$absensi->tanggal} {$absensi->jam_masuk}"),
        ]);
    } 
    public function catatIzinDiajukan(int $userId, Izin $izin): void
    {
        $mulai   = Carbon::parse($izin->tanggal_mulai)->format('d/m/Y');
        $selesai = Carbon::parse($izin->tanggal_selesai)->format('d/m/Y');

        RiwayatAktivitas::create([
            'user_id'        => $userId,
            'tipe'           => 'izin',
            'judul'          => 'Pengajuan Izin ' . ucfirst($izin->tipe),
            'deskripsi'      => "{$mulai} s/d {$selesai} ({$izin->durasi_hari} hari)" . ($izin->alasan ? " — {$izin->alasan}" : ''),
            'status'         => 'pending',
            'referensi_tipe' => 'izin',
            'referensi_id'   => $izin->id,
            'meta'           => [
                'tipe'           => $izin->tipe,
                'tanggal_mulai'  => $izin->tanggal_mulai,
                'tanggal_selesai'=> $izin->tanggal_selesai,
                'durasi_hari'    => $izin->durasi_hari,
                'alasan'         => $izin->alasan,
            ],
            'terjadi_pada'   => $izin->created_at,
        ]);
    }
 
    public function catatIzinDivalidasi(int $userId, Izin $izin): void
    {
        $mulai   = Carbon::parse($izin->tanggal_mulai)->format('d/m/Y');
        $selesai = Carbon::parse($izin->tanggal_selesai)->format('d/m/Y');
        $label   = $izin->status === 'disetujui' ? 'Disetujui' : 'Ditolak';

        RiwayatAktivitas::create([
            'user_id'        => $userId,
            'tipe'           => 'izin',
            'judul'          => "Izin " . ucfirst($izin->tipe) . " {$label}",
            'deskripsi'      => "{$mulai} s/d {$selesai}" . ($izin->catatan_admin ? " — Catatan: {$izin->catatan_admin}" : ''),
            'status'         => $izin->status,
            'referensi_tipe' => 'izin',
            'referensi_id'   => $izin->id,
            'meta'           => [
                'tipe'          => $izin->tipe,
                'catatan_admin' => $izin->catatan_admin,
                'divalidasi_pada'=> $izin->divalidasi_pada,
            ],
            'terjadi_pada'   => $izin->divalidasi_pada ?? now(),
        ]);
    }

     
    public function catatNotifWa(int $userId, string $judul, string $pesan, bool $terkirim, ?int $izinId = null): void
    {
        RiwayatAktivitas::create([
            'user_id'        => $userId,
            'tipe'           => 'notif_wa',
            'judul'          => $judul,
            'deskripsi'      => $pesan,
            'status'         => $terkirim ? 'terkirim' : 'gagal',
            'referensi_tipe' => $izinId ? 'izin' : null,
            'referensi_id'   => $izinId,
            'meta'           => ['terkirim' => $terkirim],
            'terjadi_pada'   => now(),
        ]);
    }
 
    public function getRiwayat(int $userId, array $filters = [])
    {
        $query = RiwayatAktivitas::where('user_id', $userId)->aktif();

        if (!empty($filters['tipe'])) {
            $query->where('tipe', $filters['tipe']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['bulan']) && !empty($filters['tahun'])) {
            $query->whereMonth('terjadi_pada', $filters['bulan'])
                  ->whereYear('terjadi_pada', $filters['tahun']);
        }

        return $query->orderByDesc('terjadi_pada')->paginate(20);
    }
 
    public function hapusSatu(int $userId, int $riwayatId): void
    {
        $riwayat = RiwayatAktivitas::where('user_id', $userId)->findOrFail($riwayatId);
        $riwayat->update(['dihapus' => true, 'dihapus_pada' => now()]);
    }
 
    public function hapusSemua(int $userId, ?string $tipe = null): void
    {
        $query = RiwayatAktivitas::where('user_id', $userId)->aktif();
        if ($tipe) {
            $query->where('tipe', $tipe);
        }
        $query->update(['dihapus' => true, 'dihapus_pada' => now()]);
    }
 
    public function getRingkasan(int $userId): array
    {
        $base = RiwayatAktivitas::where('user_id', $userId)->aktif();

        return [
            'total'      => (clone $base)->count(),
            'absensi'    => (clone $base)->where('tipe', 'absensi')->count(),
            'izin'       => (clone $base)->where('tipe', 'izin')->count(),
            'notif_wa'   => (clone $base)->where('tipe', 'notif_wa')->count(),
        ];
    }
}