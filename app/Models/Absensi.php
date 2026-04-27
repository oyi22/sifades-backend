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
