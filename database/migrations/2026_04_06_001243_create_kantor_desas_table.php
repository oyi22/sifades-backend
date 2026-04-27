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
        Schema::create('kantor_desas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kantor')->default('Kantor Desa');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7); 
            $table->integer('radius_meter')->default(100);
            $table->string('alamat')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kantor_desas');
    }
};
