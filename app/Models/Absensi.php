<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    protected $table = 'absensis';

    protected $fillable = [
        'user_id',
        'tanggal',
        'jam_masuk',
        'latitude',
        'longitude',
        'alamat_lokasi',
        'jarak_dari_kantor',
        'status',
        'foto_absensi',
        'skor_kepercayaan',
        'notif_wa_terkirim',
        'jam_pulang', 
        'latitude_pulang', 
        'longitude_pulang',
        'alamat_lokasi_pulang', 
        'jarak_dari_kantor_pulang',
        'foto_pulang', 
        'skor_kepercayaan_pulang', 
        'notif_wa_pulang_terkirim',
    ];

    protected function casts(){
        return [
            'tanggal' => 'date',
            'notif_wa_terkirim' => 'boolean',
            'skor_kepercayaan'  => 'float',
        ];
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
