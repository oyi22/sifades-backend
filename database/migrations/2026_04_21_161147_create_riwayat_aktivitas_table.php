<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('riwayat_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipe', ['absensi', 'izin', 'notif_wa']);
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('status')->nullable();       
            $table->string('referensi_tipe')->nullable();  
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->json('meta')->nullable();            
            $table->timestamp('terjadi_pada');
            $table->boolean('dihapus')->default(false);
            $table->timestamp('dihapus_pada')->nullable();
            $table->timestamps();

             $table->index(['user_id', 'dihapus', 'terjadi_pada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_aktivitas');
    }
};
