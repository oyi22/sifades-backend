<?php

namespace App\Jobs;

use App\Models\NotifikasiLog;
use App\Models\RiwayatAktivitas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KirimWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $backoff = 10;
 
    public function __construct(
        public int $userId,
        public string $noWa,
        public string $pesan,
        public string $tipe,         
        public ?int $izinId = null,
        public ?int $referensiId = null,
        public ?string $referensiTipe = null,
    ) {
        $this->onQueue('whatsapp');
    }
    public function handle(): void
    {
        sleep(rand(1, 3));
        $token = config('services.fonnte.token');
        $apiUrl = config('services.fonnte.url');
        $nomor  = '62' . ltrim($this->noWa, '0');
        $terkirim = false;

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post($apiUrl, [
                'target'  => $nomor,
                'message' => $this->pesan,
            ]);

            Log::info('Fonnte Response (Queue)', [
                'nomor' => $nomor,
                'sts'  => $response->status(),
                'body'  => $response->body(),
            ]);

            $terkirim = $response->successful();
        } catch (\Exception $e) {
            Log::error('Fonnte WA gagal (Queue): ', [
                'message' => $e->getMessage(),
                'nomor'   => $nomor,
            ]);
        }

        NotifikasiLog::create([
            'izin_id' => $this->izinId,
            'user_id' => $this->userId,
            'tipe' => $this->tipe,
            'pesan' => $this->pesan,
            'terkirim' => $terkirim,
            'dikirim_pada' => $terkirim ? now() : null,
        ]);

        (new RiwayatAktivitas)->catatNotifWa(
            $this->userId,
            $this->tipe,
            $this->pesan,
            $terkirim,
            $this->referensiId,
            $this->referensiTipe
        );
    }
    public function failed(\Throwable $e): void
    {
        Log::error('KirimWhatsappJob gagal total', [
            'user_id' => $this->userId,
            'error'   => $e->getMessage(),
        ]);
    }
}