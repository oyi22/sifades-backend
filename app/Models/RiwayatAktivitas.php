<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatAktivitas extends Model
{
    
    protected $table = 'riwayat_aktivitas';

    protected $fillable = [
        'user_id',
        'tipe',
        'judul',
        'deskripsi',
        'status',
        'referensi_tipe',
        'referensi_id',
        'meta',
        'terjadi_pada',
        'dihapus',
        'dihapus_pada',
    ];

    protected $casts = [
        'meta' => 'array',
        'terjadi_pada' => 'datetime',
        'dihapus_pada' => 'datetime',
        'dihapus' => 'boolean',
    ];

    public function user (){
        return $this->belongsTo(User::class);
    }

    public function scopeAktif($query){
        return $query->where('dihapus', false);
    }

    public function catatIzinDiajukan(int $userId, $izin)
    {
        return self::create([
            'user_id' => $userId,
            'tipe' => 'izin',
            'judul' => 'Pengajuan Izin',
            'deskripsi' => "Mengajukan izin {$izin->tipe}",
            'status' => 'pending',
            'referensi_tipe' => 'izin',
            'referensi_id' => $izin->id,
            'meta' => [
                'tanggal_mulai' => $izin->tanggal_mulai,
                'tanggal_selesai' => $izin->tanggal_selesai,
                'durasi' => $izin->durasi_hari,
            ],
            'terjadi_pada' => now(),
            'dihapus' => false,
        ]);
    }

    public function catatIzinDiValidasi(int $userId, $izin)
    {
        return self::create([
            'user_id' => $userId,
            'tipe' => 'izin',
            'judul' => 'Validasi Izin',
            'deskripsi' => "Izin {$izin->tipe} telah {$izin->status}",
            'status' => $izin->status,
            'referensi_tipe' => 'izin',
            'referensi_id' => $izin->id,
            'meta' => [
                'tanggal_mulai' => $izin->tanggal_mulai,
                'tanggal_selesai' => $izin->tanggal_selesai,
                'durasi' => $izin->durasi_hari,
                'catatan_admin' => $izin->catatan_admin,
            ],
            'terjadi_pada' => now(),
            'dihapus' => false,
        ]);
    }

    public function catatIzinDiperpanjang(int $userId, $izin)
    {
        return self::create([
            'user_id' => $userId,
            'tipe' => 'izin',
            'judul' => 'Perpanjangan Izin',
            'deskripsi' => "Izin {$izin->tipe} diperpanjang",
            'status' => $izin->status,
            'referensi_tipe' => 'izin',
            'referensi_id' => $izin->id,
            'meta' => [
                'tanggal_mulai' => $izin->tanggal_mulai,
                'tanggal_selesai_baru' => $izin->tanggal_selesai,
                'durasi' => $izin->durasi_hari,
            ],
            'terjadi_pada' => now(),
            'dihapus' => false,
        ]);
    }
}
