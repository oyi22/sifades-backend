<?php 
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ai.url');
    } 

    public function verify(int $userId, string $base64Image): array
    {
        try {
            $response = Http::timeout(15)->post("{$this->baseUrl}/verify", [
                'user_id' => $userId,
                'image'   => $base64Image,
            ]);

            if ($response->failed()) {
                return [
                    'verified'   => false,
                    'confidence' => 0,
                    'message'    => 'Face service tidak merespons.',
                ];
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('FaceService::verify error: ' . $e->getMessage());
            return [
                'verified'   => false,
                'confidence' => 0,
                'message'    => 'Face service error: ' . $e->getMessage(),
            ];
        }
    }
 
    public function trainSync(int $userId): array
    {
        try {
            $response = Http::timeout(120)->post("{$this->baseUrl}/train/sync", [
                'user_id' => $userId,
            ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('FaceService::train error: ' . $e->getMessage());
            throw new \Exception('Training gagal: ' . $e->getMessage());
        }
    } 
    public function detect(string $base64Image): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/detect", [
                'image' => $base64Image,
            ]);
            return $response->json();
        } catch (\Exception $e) {
            return ['success' => false, 'face_count' => 0];
        }
    }
}