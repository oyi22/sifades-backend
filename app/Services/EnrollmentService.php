<?php
namespace App\Services;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class EnrollmentService
{
    private string $aiUrl;

    public function __construct()
    {
        $this->aiUrl = config('services.ai.url', 'http://localhost:8001');
    }

    public function getStatus(int $userId, int $sesi): array
    {
        $res = Http::get("{$this->aiUrl}/enrollment/status/{$userId}/{$sesi}");
        return $res->json();
    }

    public function simpanFrame(int $userId, int $sesi, string $pose, string $frame): array
    {
        $res = Http::post("{$this->aiUrl}/enrollment/frame", [
            'user_id' => $userId,
            'sesi'    => $sesi,
            'pose'    => $pose,
            'frame'   => $frame,
        ]);

        if (!$res->successful()) {
            throw new \Exception('AI service error: ' . $res->body());
        }

        return $res->json();
    }

    public function finalize(int $userId, int $sesi): Enrollment
    { 
        $res = Http::post("{$this->aiUrl}/enrollment/finalize", [
            'user_id' => $userId,
            'sesi'    => $sesi,
        ]);

        if (!$res->successful()) {
            throw new \Exception('Gagal generate embedding: ' . $res->body());
        }

        $data = $res->json();
 
        $enrollment = Enrollment::updateOrCreate(
            ['user_id' => $userId, 'sesi' => $sesi],
            [
                'face_embedding' => $data['embedding'],
                'status'         => 'selesai',
                'path_senyum'    => "enrollments/{$userId}/sesi_{$sesi}/senyum",
                'path_kedip'     => "enrollments/{$userId}/sesi_{$sesi}/kedip",
                'path_kanan'     => "enrollments/{$userId}/sesi_{$sesi}/kanan",
                'path_kiri'      => "enrollments/{$userId}/sesi_{$sesi}/kiri",
            ]
        );
 
        $sesiSelesai = Enrollment::where('user_id', $userId)
            ->where('status', 'selesai')
            ->count();

        if ($sesiSelesai >= 2) { 
            $emb1 = Enrollment::where('user_id', $userId)->where('sesi', 1)->value('face_embedding');
            $emb2 = Enrollment::where('user_id', $userId)->where('sesi', 2)->value('face_embedding');

            $merged = array_map(
                fn($a, $b) => ($a + $b) / 2,
                $emb1, $emb2
            );
 
            $enrollment->update([
                'face_embedding' => $merged,
                'is_verified'    => true,
            ]);
        }

        return $enrollment;
    }

    public function getFinalEmbedding(int $userId): ?array
    {
        $enrollment = Enrollment::where('user_id', $userId)
            ->where('is_verified', true)
            ->latest()
            ->first();

        return $enrollment?->face_embedding;
    }

    public function tambahGagalLiveness(int $userId): int
    {
        $enrollment = Enrollment::where('user_id', $userId)
            ->where('is_verified', true)
            ->latest()
            ->first();

        if (!$enrollment) return 0;

        $enrollment->increment('gagal_liveness');
        return $enrollment->gagal_liveness;
    }

    public function resetGagalLiveness(int $userId): void
    {
        Enrollment::where('user_id', $userId)
            ->where('is_verified', true)
            ->update(['gagal_liveness' => 0]);
    }

    public function resetEnrollment(int $userId): void
    {
        // Reset di AI service
        Http::delete("{$this->aiUrl}/enrollment/reset/{$userId}");

        // Reset di DB
        Enrollment::where('user_id', $userId)->delete();
    }
}