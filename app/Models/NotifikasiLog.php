<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotifikasiLog extends Model
{
    protected $fillable = [
        'izin_id',
        'user_id',
        'tipe',
        'pesan',
        'terkirim',
        'dikirim_pada',
    ];

    protected $casts = [
        'terkirim' => 'boolean',
        'dikirm_pada' => 'datetime',
    ];

    public function izin (){
        return $this->belongsTo(Izin::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
