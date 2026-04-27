<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{ 
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('nik', 16)->unique();
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->text('alamat');
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir'); 
            $table->enum('jabatan', ['sekdes', 'kaur', 'pelayanan', 'karyawan']);
            $table->string('no_wa')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
