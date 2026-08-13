<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{ 
    public function up(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->time('jam_pulang')->nullable()->after('jam_masuk');
            $table->decimal('latitude_pulang', 10, 7)->nullable()->after('longitude');
            $table->decimal('longitude_pulang', 10, 7)->nullable()->after('latitude_pulang');
            $table->string('alamat_lokasi_pulang')->nullable()->after('alamat_lokasi');
            $table->integer('jarak_dari_kantor_pulang')->nullable()->after('jarak_dari_kantor');
            $table->string('foto_pulang')->nullable()->after('foto_absensi');
            $table->float('skor_kepercayaan_pulang')->nullable()->after('skor_kepercayaan');
            $table->boolean('notif_wa_pulang_terkirim')->default(false)->after('notif_wa_terkirim');
        });
    }
 
    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->dropColumn([
                'jam_pulang', 'latitude_pulang', 'longitude_pulang',
                'alamat_lokasi_pulang', 'jarak_dari_kantor_pulang',
                'foto_pulang', 'skor_kepercayaan_pulang', 'notif_wa_pulang_terkirim',
            ]);
        });
    }
};
