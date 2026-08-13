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
        $this->aiUrl = config('services.ai.url', 'http://localhost:5000');
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
            'valid' => $valid,
            'jarak_meter' => $jarak,
            'radius_meter' => $kantor->radius_meter,
            'nama_kantor' => $kantor->nama_kantor,
            'pesan' => $valid
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
            $tipe = $data['tipe'] ?? 'masuk';

            if (!isset($data['latitude'], $data['longitude'])) {
                throw new \Exception('data lokasi GPS wajib disertakan');
            }

            $cekLokasi = $this->validasiLokasi($data['latitude'], $data['longitude']);
            if (!$cekLokasi['valid']) {
                throw new \Exception($cekLokasi['pesan']);
            }

            return $tipe === 'pulang'
                ? $this->simpanPresensiPulang($userId, $data)
                : $this->simpanPresensiMasuk($userId, $data);
    }

    private function simpanPresensiMasuk(int $userId, array $data): Absensi
    {
        if ($this->sudahAbsenMasuk($userId)) {
            throw new \Exception('Anda sudah melakukan presensi berangkat hari ini.');
        }

        $batasMasuk = config('absensi.batas_masuk', '09:00:00');
        $status = now()->format('H:i:s') > $batasMasuk ? 'telat' : 'hadir';

        $fotoPath = null;
        if (!empty($data['foto_absensi'])) {
            $fotoPath = $this->simpanFoto($data['foto_absensi'], $userId, 'masuk');
        }

        $absensi = Absensi::create([
            'user_id'   => $userId,
            'tanggal'  => today(),
            'jam_masuk'   => now()->format('H:i:s'),
            'latitude' => $data['latitude']  ?? null,
            'longitude'  => $data['longitude'] ?? null,
            'alamat_lokasi'  => $data['alamat_lokasi']     ?? null,
            'jarak_dari_kantor' => $data['jarak_dari_kantor'] ?? null,
            'status'  => $status,  
            'foto_absensi'      => $fotoPath,
            'skor_kepercayaan'  => $data['skor_kepercayaan']  ?? null,
        ]);

        $user = User::find($userId);
        if ($user?->no_wa) {
            $pesan = "Konfirmasi presensi berangkat a.n. {$user->nama_lengkap} pada "
                . today()->format('d-m-Y') . " jam " . substr($absensi->jam_masuk, 0, 5);

            $terkirim = false;
            try {
                $this->wa->kirimNotifAbsensi($user, $absensi);
                $terkirim = true;
            } catch (\Throwable $e) {
                $terkirim = false;
            }

            $absensi->update(['notif_wa_terkirim' => $terkirim]);

            \App\Models\NotifikasiLog::create([
                'izin_id'      => null,
                'user_id'      => $userId,
                'tipe'         => 'absensi',
                'pesan'        => $pesan,
                'terkirim'     => $terkirim,
                'dikirim_pada' => $terkirim ? now() : null,
            ]);
        }

        return $absensi->load('user');
    }

    public function sudahAbsenMasuk(int $userId): bool
    {
        return Absensi::where('user_id', $userId)->whereDate('tanggal', today())->exists();
    }

    public function sudahAbsenPulang(int $userId): bool 
    {
        return Absensi::where('user_id', $userId)->whereDate('tanggal', today())->whereNotNull('jam_pulang')->exists();

    }

    private function simpanPresensiPulang(int $userId, array $data): Absensi
    {
        $batasPulang = config('absensi.batas_pulang', '15:45:00');
        if( now()->format('H:i:s') < $batasPulang){
            throw new \Exception(('presensi berangkat sudah di tutup, batas presensi berangkat pukul. ' .substr($batasPulang, 0, 5) . '.'));
        }
        $absensi = Absensi::where('user_id', $userId)
            ->whereDate('tanggal', today())
            ->first();

        if (!$absensi) {
            throw new \Exception('Anda belum melakukan presensi berangkat hari ini.');
        }

        if ($absensi->jam_pulang) {
            throw new \Exception('Anda sudah melakukan presensi pulang hari ini.');
        }

        $fotoPath = null;
        if (!empty($data['foto_absensi'])) {
            $fotoPath = $this->simpanFoto($data['foto_absensi'], $userId, 'pulang');
        }

        $absensi->update([
            'jam_pulang'               => now()->format('H:i:s'),
            'latitude_pulang'          => $data['latitude']  ?? null,
            'longitude_pulang'         => $data['longitude'] ?? null,
            'alamat_lokasi_pulang'     => $data['alamat_lokasi']     ?? null,
            'jarak_dari_kantor_pulang' => $data['jarak_dari_kantor'] ?? null,
            'foto_pulang'              => $fotoPath,
            'skor_kepercayaan_pulang'  => $data['skor_kepercayaan']  ?? null,
        ]);

        $user = User::find($userId);
        if ($user?->no_wa) {
            $pesan = "Konfirmasi presensi pulang a.n. {$user->nama_lengkap} pada "
                . today()->format('d-m-Y') . " jam " . substr($absensi->jam_pulang, 0, 5);

            $terkirim = false;
            try {
                $this->wa->kirimNotifAbsensiPulang($user, $absensi);
                $terkirim = true;
            } catch (\Throwable $e) {
                $terkirim = false;
            }

            $absensi->update(['notif_wa_pulang_terkirim' => $terkirim]);

            \App\Models\NotifikasiLog::create([
                'izin_id'      => null,
                'user_id'      => $userId,
                'tipe'         => 'absensi',
                'pesan'        => $pesan,
                'terkirim'     => $terkirim,
                'dikirim_pada' => $terkirim ? now() : null,
            ]);
        }

        return $absensi->load('user');
    }

    public function getAllForAdmin(array $filters = [])
    {
        $tipe = $filters['tipe'] ?? 'masuk';

        if ($tipe === 'pulang') {
            return $this->getPulangList($filters);
        }

        if (!empty($filters['status']) && $filters['status'] === 'alpha') {
            return $this->getAlphaList($filters);
        }

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
        if (!empty($filters['user_id'])){
            $query->where('user_id', $filters['user_id']);
        }

        return $query->latest('tanggal')->paginate(15);
    }

    private function getPulangList(array $filters)
    {
        $tanggal = $filters['tanggal'] ?? today()->toDateString();

        $query = Absensi::with('user')->whereDate('tanggal', $tanggal);

        if(!empty($filters['user_id'])){
            $query->where('user_id', $filters['user_id']);
        }
 
        if (!empty($filters['status']) && $filters['status'] === 'hadir') {
            $query->whereNotNull('jam_pulang');
        } elseif (!empty($filters['status']) && $filters['status'] === 'alpha') {
            $query->whereNull('jam_pulang');
        }

        $paginated = $query->latest('tanggal')->paginate(15);

        $paginated->getCollection()->transform(function ($a) {
            return (object) [
                'id'                => $a->id,
                'user'              => $a->user,
                'tanggal'           => $a->tanggal,
                'jam_masuk'         => $a->jam_pulang,               
                'alamat_lokasi'     => $a->alamat_lokasi_pulang,      
                'skor_kepercayaan'  => $a->skor_kepercayaan_pulang,   
                'foto_absensi'      => $a->foto_pulang,              
                'status'            => $a->jam_pulang ? 'hadir' : 'alpha',
            ];
        });

        return $paginated;
    }

    public function getRekapHarian(string $tanggal, string $tipe = 'masuk', ?int $userId = null): array
    {
        if ($tipe === 'pulang') {
            $baseQuery = fn() => Absensi::whereDate('tanggal', $tanggal)
                ->when($userId, fn($q) => $q->where('user_id', $userId));

            $sudahMasuk  = $baseQuery()->count();
            $sudahPulang = $baseQuery()->whereNotNull('jam_pulang')->count();

            return [
                'tanggal'    => $tanggal,
                'user_id'    => $userId,
                'total_user' => $sudahMasuk,
                'hadir'      => $sudahPulang,
                'izin'       => 0,
                'alpha'      => max(0, $sudahMasuk - $sudahPulang),
            ];
        }

        $total = $userId ? User::where('id', $userId)->count() : User::count();

        $baseQuery = fn() => Absensi::whereDate('tanggal', $tanggal)
            ->when($userId, fn($q) => $q->where('user_id', $userId));

        $hadir = $baseQuery()->where('status', 'hadir')->count();
        $telat = $baseQuery()->where('status', 'telat')->count();
        $izin  = $baseQuery()->where('status', 'izin')->count();

        return [
            'tanggal'    => $tanggal,
            'user_id'    => $userId,
            'total_user' => $total,
            'hadir'      => $hadir,
            'telat'      => $telat,
            'izin'       => $izin,
            'alpha'      => max(0, $total - $hadir - $telat - $izin),
        ];
    }

    private function getAlphaList(array $filters)
    {
        $tanggal = $filters['tanggal'] ?? today()->toDateString();

        $userQuery = User::whereDoesntHave('absensis', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->whereDoesntHave('izins', function ($q) use ($tanggal) {
                $q->where('status', 'disetujui')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal);
            });

        if (!empty($filters['user_id'])) {
            $userQuery->where('id', $filters['user_id']);
        }

        $userAlpha = $userQuery->paginate(15);

        $userAlpha->getCollection()->transform(function ($user) use ($tanggal) {
            return (object) [
                'id' => null,
                'user' => $user,
                'tanggal' => $tanggal,
                'jam_masuk' => null,
                'status' => 'alpha',
                'alamat_lokasi' => null,
                'skor_kepercayaan' => null,
                'foto_absensi' => null,
            ];
        });

        return $userAlpha;
    }

    private function simpanFoto(string $base64, int $userId, string $tipe = 'masuk'): string
    {
        $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        $filename = "absensi/{$userId}/{$tipe}_" . now()->format('Ymd_His') . '.jpg';
        Storage::disk('public')->put($filename, $image);
        return $filename;
    }
}