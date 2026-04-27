<?php
namespace App\Services;

use App\Models\Absensi;
use App\Models\KantorDesa;
use App\Models\RiwayatAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AbsensiService
{
    private string $aiUrl;

    public function __construct(
        protected WhatsappService   $wa,
        protected EnrollmentService $enrollment,
        protected RiwayatAktivitas $riwayat,
    ) {
        $this->aiUrl = config('services.ai.url', 'http://localhost:8001');
    }
 
    public function validasiLokasi(float $lat, float $lng): array
    {
        $kantor = KantorDesa::aktif();

        if (!$kantor) {
            return ['valid' => false, 'pesan' => 'Lokasi kantor belum dikonfigurasi oleh admin.'];
        }

        $jarak = $kantor->hitungJarak($lat, $lng);
        $valid = $kantor->dalamRadius($lat, $lng);

        return [
            'valid'          => $valid,
            'jarak_meter'    => $jarak,
            'radius_meter'   => $kantor->radius_meter,
            'nama_kantor'    => $kantor->nama_kantor,
            'pesan'          => $valid
                ? "Anda berada di area {$kantor->nama_kantor} ({$jarak}m)"
                : "Anda berada di luar area absensi ({$jarak}m dari kantor, max {$kantor->radius_meter}m)",
        ];
    }
 
    public function analisisLiveness(int $userId, string $frame): array
    {
        $res = Http::post("{$this->aiUrl}/recognition/liveness/frame", [
            'user_id' => $userId,
            'frame'   => $frame,
        ]);

        if (!$res->successful()) {
            throw new \Exception('AI service error');
        }

        return $res->json();
    }
 
    public function verifikasiWajah(int $userId, string $frame): array
    {
        $embedding = $this->enrollment->getFinalEmbedding($userId);

        if (!$embedding) {
            throw new \Exception('Data enrollment tidak ditemukan. Silakan enrollment ulang.');
        }

        $res = Http::post("{$this->aiUrl}/recognition/verify", [
            'user_id'   => $userId,
            'frame'     => $frame,
            'embedding' => $embedding,
        ]);

        if (!$res->successful()) {
            throw new \Exception('AI service error saat verifikasi wajah');
        }

        $result = $res->json();

        if (!$result['match']) {
            $gagal = $this->enrollment->tambahGagalLiveness($userId);
            $result['gagal_count'] = $gagal;
            $result['perlu_enrollment_ulang'] = $gagal >= 3;
        } else {
            $this->enrollment->resetGagalLiveness($userId);
            $result['gagal_count'] = 0;
            $result['perlu_enrollment_ulang'] = false;
        }

        return $result;
    }
 
    public function simpanAbsensi(int $userId, array $data): Absensi
    {
        if ($this->sudahAbsenHariIni($userId)) {
            throw new \Exception('Anda sudah melakukan absensi hari ini.');
        }

        $fotoPath = null;
        if (!empty($data['foto_absensi'])) {
            $fotoPath = $this->simpanFoto($data['foto_absensi'], $userId);
        }

        $absensi = Absensi::create([
            'user_id' => $userId,
            'tanggal'  => today(),
            'jam_masuk'   => now()->format('H:i:s'),
            'latitude' => $data['latitude']  ?? null,
            'longitude' => $data['longitude'] ?? null,
            'alamat_lokasi' => $data['alamat_lokasi']     ?? null,
            'jarak_dari_kantor' => $data['jarak_dari_kantor'] ?? null,
            'status' => 'hadir',
            'foto_absensi'  => $fotoPath,
            'skor_kepercayaan'  => $data['skor_kepercayaan']  ?? null,
        ]);

        $this->riwayat->catatAbsensi($userId, $absensi);
 
        $user = User::find($userId);
        if ($user?->no_wa) {
            $this->wa->kirimNotifAbsensi($user, $absensi);
            $absensi->update(['notif_wa_terkirim' => true]);
        }

        return $absensi->load('user');
    }

    public function sudahAbsenHariIni(int $userId): bool
    {
        return Absensi::where('user_id', $userId)->whereDate('tanggal', today())->exists();
    }

    public function getAllForAdmin(array $filters = [])
    {
        $query = Absensi::with('user');

        if (!empty($filters['tanggal'])) {
            $query->whereDate('tanggal', $filters['tanggal']);
        }
        if (!empty($filters['bulan']) && !empty($filters['tahun'])) {
            $query->whereMonth('tanggal', $filters['bulan'])->whereYear('tanggal', $filters['tahun']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('tanggal')->paginate(15);
    }

    public function getRekapHarian(string $tanggal): array
    {
        $total  = User::count();
        $hadir  = Absensi::whereDate('tanggal', $tanggal)->where('status', 'hadir')->count();
        $izin   = Absensi::whereDate('tanggal', $tanggal)->where('status', 'izin')->count();

        return [
            'tanggal' => $tanggal,
            'total_user' => $total,
            'hadir' => $hadir,
            'izin' => $izin,
            'alpha' => max(0, $total - $hadir - $izin),
        ];
    }

    private function simpanFoto(string $base64, int $userId): string
    {
        $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        $filename = "absensi/{$userId}/" . now()->format('Ymd_His') . '.jpg';
        Storage::disk('public')->put($filename, $image);
        return $filename;
    }
}