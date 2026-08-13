<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE absensis MODIFY COLUMN status ENUM('hadir','telat','izin','alpha') NOT NULL DEFAULT 'hadir'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE absensis MODIFY COLUMN status ENUM('hadir','izin','alpha') NOT NULL DEFAULT 'hadir'");
    }
};