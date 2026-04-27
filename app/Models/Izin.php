<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin;

/**
 * @property \Carbon\Carbon $tanggal_mulai
 * @property \Carbon\Carbon $tanggal_selesai
 * @property \Carbon\Carbon|null $tanggal_selesai_asli
 * @property \Carbon\Carbon|null $divalidasi_pada
 */
class Izin extends Model
{
   
    protected $table = 'izins';

    protected $fillable = [
        'user_id', 
        'tipe', 
        'tanggal_mulai',
        'tanggal_selesai',
        'durasi_hari', 
        'alasan',
        'file_surat', 
        'status',
        'catatan_admin', 
        'divalidasi_oleh',
        'divalidasi_pada',
        'sudah_diperpanjang',
        'tanggal_selesai_asli',
        'notif_wa_pengajuan', 
        'notif_wa_validasi',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'tanggal_selesai_asli' => 'date',
        'divalidasi_pada' => 'datetime',
        'sudah_diperpanjang' => 'boolean',
    ];
    protected $appends = ['file_url'];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function validator(){
        return $this->belongsTo(Admin::class, 'divalidasi_oleh');
    }

    public function isAktif(): bool {
        $today = today();
        return $today->between(
            $this->tanggal_mulai, 
            $this->tanggal_selesai
        ) && $this->status === 'disetujui';
    }

    public function getFileUrlAttribute(){
        return $this->file_surat ? asset('storage/' .$this->file_surat) : null;
    }
}
