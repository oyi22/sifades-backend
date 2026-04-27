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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('sesi')->default(1);
            $table->string('path_senyum')->nullable();
            $table->string('path_kedip')->nullable();
            $table->string('path_kanan')->nullable();
            $table->string('path_kiri')->nullable();
            $table->longText('face_embedding')->nullable();
            $table->enum('status', ['pending', 'selesai', 'gagal'])->default('pending');
            $table->tinyInteger('gagal_liveness')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
