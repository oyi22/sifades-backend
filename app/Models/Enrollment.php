<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'user_id',
        'sesi',
        'path_senyum',
        'path_kedip',
        'path_kanan',
        'path_kiri',
        'face_embedding',
        'status',
        'gagal_liveness',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'face_embedding' => 'array',    
            'is_verified'    => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
 
    public function semuaPoseSelesai(): bool
    {
        return $this->path_senyum
            && $this->path_kedip
            && $this->path_kanan
            && $this->path_kiri;
    }
}