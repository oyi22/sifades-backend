<?php

namespace Database\Seeders; 

use Illuminate\Database\Seeder; 
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmbeddingSeeder extends Seeder
{ 
    public function run(): void
    { 
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $path = storage_path('app/face_embedding.json');

        if(!file_exists($path)){
            $this->command->error('FILE TIDAK DITEMUKAN : ' . $path);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            return;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if(!$data){
            $this->command->error('GAGAL PARSE JSON');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            return;
        }

        $namaTabel = 'embedding';
        DB::statement("TRUNCATE TABLE {$namaTabel}");

        $this->command->info('=== MULAI PROSES IMPORT EMBEDDING ===');
        
        $totalRowInserted = 0;
        $skip = 0;

        foreach ($data as $item) {
            $namaUserJson = $item['nama_user'] ?? null;
            $allEmb = $item['face_embeddings'] ?? null;

            if(!$namaUserJson || !$allEmb){
                $skip++;
                continue;
            }
 
            $namaDicari = str_replace('_', ' ', $namaUserJson);
            $user = DB::table('users')->where('nama_lengkap', 'LIKE', '%' . $namaDicari . '%')->first();

            if(!$user){
                $this->command->warn("[-] User '{$namaDicari}' TIDAK DITEMUKAN di DB Web. (Di-skip)");
                $skip++;
                continue;
            }

            $userId = $user->id; 
            $userBatch = [];
            $now = Carbon::now()->toDateTimeString();  

            $this->command->info("[+] Memasukkan data untuk: {$user->nama_lengkap} -> Menggunakan ID: {$userId}");
 
            if (isset($allEmb[0]) && is_array($allEmb[0])){
                foreach($allEmb as $singleEmb){
                    $cleanEmb = array_filter($singleEmb, fn($v) => is_numeric($v));
                    $cleanEmb = array_values($cleanEmb);

                    if (count($cleanEmb) > 0) {
                        $userBatch[] = [
                            'user_id' => $userId,
                            'face_embeddings' => json_encode($cleanEmb),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $totalRowInserted++;
                    }
                }
            }  
            else { 
                $cleanEmb = array_filter($allEmb, fn($v) => is_numeric($v));
                $cleanEmb = array_values($cleanEmb);

                if (count($cleanEmb) > 0) {
                    $userBatch[] = [
                        'user_id' => $userId,
                        'face_embeddings' => json_encode($cleanEmb),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $totalRowInserted++;
                }
            }
 
            if(!empty($userBatch)){
                DB::table($namaTabel)->insert($userBatch);
            }
        }
 
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info("==================================================");
        $this->command->info("SELESAI IMPORT TANPA BUG!");
        $this->command->comment("Total Baris Berhasil Masuk DB: {$totalRowInserted} baris.");
        $this->command->error("Total Data Di-skip: {$skip}");
        $this->command->info("==================================================");
    }
}