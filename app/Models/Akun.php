<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Sanctum\HasApiTokens;

class Akun extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'user_id',
        'username',
        'password',
        'is_active',
        'last_login'
    ];

    protected function casts():array
    {
        return [ 
            'is_active' => 'boolean', 
            'last_login' => 'datetime',
        ];
    }

    public function user (){
        return $this->belongsTo(User::class);
    }

    public function getPasswordPlain(){
        return $this->attributes['password'];
    }
}
