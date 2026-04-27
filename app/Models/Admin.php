<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    use HasApiTokens;
    protected $table = 'admins';
    protected $fillable = [
        'nama', 
        'username', 
        'password'
    ]; 


    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
    public function izinDivalidasi()
    {
        return $this->hasMany(Izin::class, 'divalidasi_oleh');
    }
}
