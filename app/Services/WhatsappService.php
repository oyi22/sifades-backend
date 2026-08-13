<?php
namespace App\Services;

use App\Jobs\KirimWhatsappJob;
use App\Models\Absensi;
use App\Models\Izin;
use App\Models\User;
use Carbon\Carbon;
use Faker\Core\Barcode;
use Illuminate\Support\Facades\Cache;

class WhatsappService
{
    public function kirimNotifAbsensi(User $user, Absensi $absensi): void
    {
        $tgl  = Carbon::parse($absensi->tanggal);
        $hari = $this->namaHari($tgl->dayOfWeek);
        $tgl  = $tgl->format('d/m/Y');
        $jam  = Carbon::parse($absensi->jam_masuk)->format('H:i');

        $pesan  = "Halo, *{$user->nama_lengkap}!* 👋\n\n";
        $pesan .= "✅ *Absensi Berhasil*\n";
        $pesan .= "📅 {$hari}, {$tgl}\n";
        $pesan .= "⏰ Pukul {$jam} WIB\n";
        $pesan .= "📍 {$absensi->alamat_lokasi}\n\n";
        $pesan .= "Terima kasih telah hadir! 🙏";

        $this->antrekan($user, $pesan, 'absensi', null, $absensi->id, 'absensi');
    }

    public function kirimNotifIzinDiajukan(User $user, Izin $izin): void
    {
        $mulai   = Carbon::parse($izin->tanggal_mulai)->format('d/m/Y');
        $selesai = Carbon::parse($izin->tanggal_selesai)->format('d/m/Y');
        $tipe    = strtoupper($izin->tipe);

        $pesan  = "Halo, *{$user->nama_lengkap}!* 👋\n\n";
        $pesan .= "*Pengajuan Izin Diterima Sistem*\n";
        $pesan .= "Tipe: *{$tipe}*\n";
        $pesan .= "{$mulai} s/d {$selesai} ({$izin->durasi_hari} hari)\n";

        if ($izin->alasan) {
            $pesan .= " Alasan: _{$izin->alasan}_\n";
        }

        $pesan .= "\nIzin Anda sedang menunggu validasi admin.";

        $this->antrekan($user, $pesan, 'pengajuan', $izin->id, $izin->id, 'izin');
    }

    public function kirimNotifValidasiIzin(User $user, Izin $izin): void
    {
        $mulai   = Carbon::parse($izin->tanggal_mulai)->format('d/m/Y');
        $selesai = Carbon::parse($izin->tanggal_selesai)->format('d/m/Y');
        $tipe    = strtoupper($izin->tipe);
        $status  = $izin->status === 'disetujui' ? 'Disetujui' : 'Ditolak';

        $pesan  = "Halo, *{$user->nama_lengkap}!* 👋\n\n";
        $pesan .= " *Update Izin {$tipe}*\n";
        $pesan .= " {$mulai} s/d {$selesai} ({$izin->durasi_hari} hari)\n";
        $pesan .= "Status: *{$status}*\n";

        if ($izin->catatan_admin) {
            $pesan .= "Catatan Admin: _{$izin->catatan_admin}_\n";
        }

        $pesan .= "\nTerima kasih. 🙏";

        $tipeLog = $izin->status === 'disetujui' ? 'disetujui' : 'ditolak';

        $this->antrekan($user, $pesan, $tipeLog, $izin->id, $izin->id, 'izin');
    }

    public function kirimNotifAbsensiPulang(User $user, Absensi $absensi): void
    {
        $tgl  = Carbon::parse($absensi->tanggal);
        $hari = $this->namaHari($tgl->dayOfWeek);
        $tgl  = $tgl->format('d/m/Y');
        $jam  = Carbon::parse($absensi->jam_pulang)->format('H:i');

        $pesan  = "Halo, *{$user->nama_lengkap}!* 👋\n\n";
        $pesan .= "✅ *Absensi Pulang Berhasil*\n";
        $pesan .= "📅 {$hari}, {$tgl}\n";
        $pesan .= "⏰ Pukul {$jam} WIB\n";
        $pesan .= "📍 {$absensi->alamat_lokasi_pulang}\n\n";
        $pesan .= "Terima kasih, hati-hati di jalan! 🙏";

        $this->antrekan($user, $pesan, 'absensi_pulang', null, $absensi->id, 'absensi');
    }
 
    private function antrekan(User $user, string $pesan, string $tipe, ?int $izinId, ?int $refId, ?string $refTipe): void
    {
        $lockKey = 'wa_global_slot_lock';
        $slotKey = 'wa_global_next_slot';

        $lock = Cache::lock($lockKey, 5);

        $waktuKirim = $lock->block(5, function () use ($slotKey) {
            $now = now();
            $nextSlot = Cache::get($slotKey);
            $nextSlot = $nextSlot ? Carbon::parse($nextSlot) : $now;

            if ($nextSlot->lt($now)) {
                $nextSlot = $now->copy();
            }

            $jedaDetik = rand(7, 8);
            Cache::put($slotKey, $nextSlot->copy()->addSeconds($jedaDetik), now()->addMinutes(30));

            return $nextSlot;
        });

        KirimWhatsappJob::dispatch(
            $user->id,
            $user->no_wa,
            $pesan,
            $tipe,
            $izinId,
            $refId,
            $refTipe
        )->delay($waktuKirim);
    }

    private function namaHari(int $day): string
    {
        return ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][$day];
    }
}