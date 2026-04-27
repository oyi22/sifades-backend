<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{ 
    public function up(): void
    {
        Schema::create('notifikasi_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('izin_id')->constrained('izins')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipe', ['pengajuan', 'disetujui', 'ditolak']);
            $table->text('pesan');
            $table->boolean('terkirim')->default(false);
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('notifikasi_logs');
    }
};
