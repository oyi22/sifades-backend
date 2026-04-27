<?php 
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class TrainingService
{ 
    const POSES = ['netral', 'senyum', 'kedip', 'kanan', 'kiri'];

    public function getPoseStatus(int $userId): array
    {
        $user = User::findOrFail($userId);
        $path = "training/{$userId}";

        $status = [];
        foreach (self::POSES as $pose) {
            $files = Storage::disk('public')->files("{$path}/{$pose}");
            $status[$pose] = count($files);
        }

        $selesai = collect($status)->every(fn($count) => $count >= 30);

        return [
            'poses'          => $status,
            'selesai'        => $selesai,
            'sudah_training' => $user->sudah_training,
        ];
    }

    public function simpanFrameTraining(int $userId, string $pose, string $base64Image): array
    {
        if (!in_array($pose, self::POSES)) {
            throw new \Exception("Pose '{$pose}' tidak valid.");
        }

        $path  = "training/{$userId}/{$pose}";
        $files = Storage::disk('public')->files($path);

        if (count($files) >= 30) {
            return ['sukses' => true, 'jumlah' => count($files), 'pesan' => 'Pose ini sudah selesai.'];
        }

        $image    = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
        $filename = "{$path}/" . now()->format('Ymd_His_') . uniqid() . '.jpg';
        Storage::disk('public')->put($filename, $image);

        $files  = Storage::disk('public')->files($path);
        $jumlah = count($files);

        return [
            'sukses' => true,
            'pose' => $pose,
            'jumlah' => $jumlah,
            'target' => 30,
            'pesan' => $jumlah >= 30 ? 'Pose selesai!' : "Frame {$jumlah}/30",
        ];
    }

    public function selesaikanTraining(int $userId): User
    {
        $status = $this->getPoseStatus($userId);

        if (!$status['selesai']) {
            throw new \Exception('Training belum lengkap. Semua pose harus minimal 30 frame.');
        }

        $faceService = app(\App\Services\FaceService::class);
        $faceService->trainSync($userId);

        $user = User::findOrFail($userId);
        $user->update([
            'sudah_training'     => true,
            'training_data_path' => "training/{$userId}",
        ]);

        return $user;
    }

    public function resetTraining(int $userId): void
    {
        $path = "training/{$userId}";
        Storage::disk('public')->deleteDirectory($path);

        User::findOrFail($userId)->update([
            'sudah_training'     => false,
            'training_data_path' => null,
        ]);
    }
}