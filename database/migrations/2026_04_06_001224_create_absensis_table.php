<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{ 
    public function up(): void
    {
        Schema::create('absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('tanggal');
            $table->time('jam_masuk');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('alamat_lokasi')->nullable();
            $table->integer('jarak_dari_kantor')->nullable();
            $table->enum('status', ['hadir', 'izin', 'alpha'])->default('hadir');
            $table->string('foto_absensi')->nullable();
            $table->float('skor_kepercayaan')->nullable();
            $table->boolean('notif_wa_terkirim')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('absensis');
    }
};
