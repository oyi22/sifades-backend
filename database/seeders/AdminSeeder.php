<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{ 
    public function run(): void
    {
        Admin::firstOrCreate(
            ['username' => 'admindev2026'],
            [
                'nama' => 'Admin Dev',
                'password' => Hash::make('SayaLupa'),
            ]
        );
    }
}
