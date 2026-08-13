<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN jabatan ENUM(
                'sekdes',
                'kaur',
                'pelayanan',
                'karyawan',
                'kamituwo',
                'kades',
                'pj'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN jabatan ENUM(
                'sekdes',
                'kaur',
                'pelayanan',
                'karyawan',
                'kamituwo'
            ) NOT NULL
        ");
    }
};