<?php
namespace App\Services;

use App\Models\Izin;
use App\Models\IzinQuota;
use App\Models\RiwayatAktivitas;
use App\Models\User;
use Carbon\Carbon; 
use Illuminate\Support\Facades\Log;

class IzinService
{
    const MAX_HARI_DINAS  = 2;
    const MAX_HARI_SAKIT  = 5;
    const MAX_HARI_LAINNYA = 2;
    const SLOT_PER_BULAN  = 3; 

    public function __construct(
        protected WhatsappService $wa,
        protected RiwayatAktivitas $riwayat,
    ) {}

    public function ajukanIzin(int $userId, array $data): array
    {
        $tipe        = $data['tipe'];
        $mulai       = Carbon::parse($data['tanggal_mulai']);
        $selesai     = Carbon::parse($data['tanggal_selesai']);
        $durasiHari  = $mulai->diffInDays($selesai) + 1;

        $maxHari = match($tipe) {
            'dinas'   => self::MAX_HARI_DINAS,
            'sakit'   => self::MAX_HARI_SAKIT,
            'lainnya' => self::MAX_HARI_LAINNYA,
        };

        if ($durasiHari > $maxHari) {
            throw new \Exception("Maksimal izin {$tipe} adalah {$maxHari} hari.");
        }

        if ($mulai->lt(today())) {
            throw new \Exception('Tanggal mulai tidak boleh sebelum hari ini.');
        }

        $izinAktif = Izin::where('user_id', $userId)
            ->whereIn('status', ['pending', 'disetujui'])
            ->where(function ($q) use ($mulai, $selesai) {
                $q->whereBetween('tanggal_mulai', [$mulai, $selesai])
                  ->orWhereBetween('tanggal_selesai', [$mulai, $selesai])
                  ->orWhere(function ($q2) use ($mulai, $selesai) {
                      $q2->where('tanggal_mulai', '<=', $mulai)
                         ->where('tanggal_selesai', '>=', $selesai);
                  });
            })
            ->exists();

        if ($izinAktif) {
            throw new \Exception('Anda masih memiliki izin aktif atau pending dalam periode tersebut.');
        }

        $alerts = [];
        if ($tipe !== 'dinas') {
            $bulanMulai   = (int) $mulai->format('m');
            $tahunMulai   = (int) $mulai->format('Y');
            $bulanSelesai = (int) $selesai->format('m');
            $tahunSelesai = (int) $selesai->format('Y');

            $quotaMulai = $this->getOrCreateQuota($userId, $bulanMulai, $tahunMulai);

            if ($quotaMulai->sisa_slot <= 0) {
                throw new \Exception("Kuota izin bulan ini sudah habis.");
            }

            $quotaMulai->decrement('sisa_slot');

            if ($bulanMulai !== $bulanSelesai || $tahunMulai !== $tahunSelesai) {
                $quotaSelesai = $this->getOrCreateQuota($userId, $bulanSelesai, $tahunSelesai);

                if ($quotaSelesai->sisa_slot <= 0) {
                    $quotaMulai->increment('sisa_slot');
                    throw new \Exception("Kuota izin bulan berikutnya sudah habis.");
                }

                $quotaSelesai->decrement('sisa_slot');
                $alerts[] = "Izin Anda melewati bulan berikutnya dan akan mengurangi kuota menjadi {$quotaSelesai->sisa_slot} slot";
            }
        } 

        $filePath = null;
        if (isset($data['file_surat'])) {
            $file     = $data['file_surat'];
            $ext      = $file->getClientOriginalExtension();
            $filePath = $file->storeAs(
                "izin/{$userId}",
                'surat_' . time() . '.' . $ext,
                'public'
            );
        }
 
        $izin = Izin::create([
            'user_id' => $userId,
            'tipe' => $tipe,
            'tanggal_mulai'  => $mulai,
            'tanggal_selesai'=> $selesai,
            'durasi_hari' => $durasiHari,
            'alasan' => $data['alasan'] ?? null,
            'file_surat' => $filePath,
            'status' => 'pending',
        ]);

        try {
            $this->riwayat->catatIzinDiajukan($userId, $izin);
        }catch (\Throwable $e){
            \Illuminate\Support\Facades\Log::error('gagal catat riwayat izin', [
                'error' => $e->getMessage() 
            ]);
        }

        $user = \App\Models\User::find($userId);
        if ($user && $user->no_wa){
            $this->wa->kirimNotifIzinDiajukan($user, $izin);
            $izin->update(['notif_wa_pengajuan' => true]);
        }

        return [
            'izin' => $izin, 
            'alerts' => $alerts
        ];
    }
 
    public function validasi(int $izinId, int $adminId, string $status, ?string $catatan): Izin
    {
        $izin = Izin::with('user')->findOrFail($izinId);

        if ($izin->status !== 'pending') {
            throw new \Exception('Izin ini sudah divalidasi sebelumnya.');
        }

        $izin->update([
            'status' => $status,
            'catatan_admin' => $catatan,
            'divalidasi_oleh' => $adminId,
            'divalidasi_pada' => now(),
        ]);
        $this->riwayat->catatIzinDiValidasi($izin->user_id, $izin->fresh());
 
        $user = $izin->fresh()->user;
        if ($user && $user->no_wa) {
            $this->wa->kirimNotifValidasiIzin($user, $izin->fresh());
            $izin->update(['notif_wa_validasi' => true]);
        }
 
        if ($status === 'ditolak' && $izin->tipe !== 'dinas') {
            $this->kembalikanSlot($izin);
        }

        return $izin->load(['user', 'validator']);
    }
 
    public function perpanjang(int $izinId, int $adminId, string $tanggalSelesaiBaru): Izin
    {
        $izin = Izin::with('user')->findOrFail($izinId);

        if ($izin->tipe !== 'sakit') {
            throw new \Exception('Hanya izin sakit yang dapat diperpanjang.');
        }

        if ($izin->status !== 'disetujui') {
            throw new \Exception('Izin harus berstatus disetujui untuk diperpanjang.');
        }

        if ($izin->sudah_diperpanjang) {
            throw new \Exception('Izin ini sudah pernah diperpanjang.');
        }

        $selesaiBaru = Carbon::parse($tanggalSelesaiBaru);
        if ($selesaiBaru->lte($izin->tanggal_selesai)) {
            throw new \Exception('Tanggal selesai baru harus setelah tanggal selesai saat ini.');
        }

        $izin->update([
            'tanggal_selesai_asli' => $izin->tanggal_selesai,
            'tanggal_selesai' => $selesaiBaru,
            'durasi_hari' => $izin->tanggal_mulai->diffInDays($selesaiBaru) + 1,
            'sudah_diperpanjang' => true,
        ]);

        return $izin->fresh()->load(['user', 'validator']);
    }
 
    private function getOrCreateQuota(int $userId, int $bulan, int $tahun): IzinQuota
    {
        return IzinQuota::firstOrCreate(
            ['user_id' => $userId, 'bulan' => $bulan, 'tahun' => $tahun],
            ['sisa_slot' => self::SLOT_PER_BULAN]
        );
    }

    private function kembalikanSlot(Izin $izin): void
    {
        $bulan = (int) $izin->tanggal_mulai->format('m');
        $tahun = (int) $izin->tanggal_mulai->format('Y');
        $quota = $this->getOrCreateQuota($izin->user_id, $bulan, $tahun);
        if ($quota->sisa_slot < self::SLOT_PER_BULAN) {
            $quota->increment('sisa_slot');
        }
 
        $bulanSelesai = (int) $izin->tanggal_selesai->format('m');
        $tahunSelesai = (int) $izin->tanggal_selesai->format('Y');
        if ($bulan !== $bulanSelesai || $tahun !== $tahunSelesai) {
            $quotaNext = $this->getOrCreateQuota($izin->user_id, $bulanSelesai, $tahunSelesai);
            if ($quotaNext->sisa_slot < self::SLOT_PER_BULAN) {
                $quotaNext->increment('sisa_slot');
            }
        }
    } 

    public function getRiwayatUser(int $userId)
    {
        return Izin::where('user_id', $userId)
            ->latest('tanggal_mulai')
            ->paginate(10);
    }

    public function getAllForAdmin(array $filters = [])
    {
        $query = Izin::with(['user', 'validator']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['tipe'])) {
            $query->where('tipe', $filters['tipe']);
        }
        if (!empty($filters['tanggal'])) {
            $query->whereDate('tanggal_mulai', $filters['tanggal']);
        }

        return $query->latest()->paginate(15);
    }

    public function getSisaSlot(int $userId): int
    {
        $bulan = (int) now()->format('m');
        $tahun = (int) now()->format('Y');
        $quota = $this->getOrCreateQuota($userId, $bulan, $tahun);
        return $quota->sisa_slot;
    }

    public function getIzinAktif(int $userId): ?Izin
    {
        return Izin::where('user_id', $userId)
            ->whereIn('status', ['pending', 'disetujui'])
            ->where('tanggal_mulai', '<=', today())
            ->where('tanggal_selesai', '>=', today())
            ->first();
    }
}