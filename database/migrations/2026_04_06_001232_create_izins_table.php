<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{ 
    public function up(): void
    {
        Schema::create('izins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipe', ['dinas', 'sakit', 'lainnya']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->unsignedTinyInteger('durasi_hari');
            $table->text('alasan')->nullable();
            $table->string('file_surat')->nullable();
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->text('catatan_admin')->nullable();
            $table->foreignId('divalidasi_oleh')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamp('divalidasi_pada')->nullable();
            $table->boolean('sudah_diperpanjang')->default(false);
            $table->date('tanggal_selesai_asli')->nullable();
            $table->boolean('notif_wa_pengajuan')->default(false);
            $table->boolean('notif_wa_validasi')->default(false);
            $table->timestamps();  
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('izins');
    }
};
