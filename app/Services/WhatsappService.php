<?php
namespace App\Services;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\User;
use App\Models\NotifikasiLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    private string $token;
    private string $apiUrl = 'https://api.fonnte.com/send';

    public function __construct()
    {
        $this->token  = config('services.fonnte.token');
        $this->apiUrl = config('services.fonnte.url');
    }

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

        $terkirim = $this->kirim($user->no_wa, $pesan);
 
        NotifikasiLog::create([
            'user_id'     => $user->id,
            'tipe'        => 'absensi',
            'pesan'       => $pesan,
            'terkirim'    => $terkirim,
            'dikirim_pada' => $terkirim ? now() : null,
        ]);
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

        $terkirim = $this->kirim($user->no_wa, $pesan);
 
        NotifikasiLog::create([
            'izin_id'     => $izin->id,
            'user_id'     => $user->id,
            'tipe'        => 'pengajuan',
            'pesan'       => $pesan,
            'terkirim'    => $terkirim,
            'dikirim_pada' => $terkirim ? now() : null,
        ]);
    }

    public function kirimNotifValidasiIzin(User $user, Izin $izin): void
    {
        $mulai   = Carbon::parse($izin->tanggal_mulai)->format('d/m/Y');
        $selesai = Carbon::parse($izin->tanggal_selesai)->format('d/m/Y');
        $tipe    = strtoupper($izin->tipe);
        $status  = $izin->status === 'disetujui' ? 'Disetujui' : 'Ditolak';

        $pesan  = "Halo, *{$user->nama_lengkap}!* 👋\n\n";
        $pesan .= "📋 *Update Izin {$tipe}*\n";
        $pesan .= "📅 {$mulai} s/d {$selesai} ({$izin->durasi_hari} hari)\n";
        $pesan .= "Status: *{$status}*\n";

        if ($izin->catatan_admin) {
            $pesan .= "Catatan Admin: _{$izin->catatan_admin}_\n";
        }

        $pesan .= "\nTerima kasih. 🙏";

        $terkirim = $this->kirim($user->no_wa, $pesan);
 
        NotifikasiLog::create([
            'izin_id'     => $izin->id,
            'user_id'     => $user->id,
            'tipe'        => $izin->status === 'disetujui' ? 'disetujui' : 'ditolak',
            'pesan'       => $pesan,
            'terkirim'    => $terkirim,
            'dikirim_pada' => $terkirim ? now() : null,
        ]);
    }

    private function kirim(string $noWa, string $pesan): bool
    {
        $nomor = '62' . ltrim($noWa, '0');

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->post($this->apiUrl, [
                'target'  => $nomor,
                'message' => $pesan,
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Fonnte WA gagal: ' . $e->getMessage());
            return false;
        }
    }

    private function namaHari(int $day): string
    {
        return ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][$day];
    }
}