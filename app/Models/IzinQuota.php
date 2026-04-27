<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class IzinQuota extends Model
{
    protected $fillable = [
        'user_id', 
        'bulan', 
        'tahun', 
        'sisa_slot'
    ];

    public function user (){
        return $this->belongsTo(User::class);
    }
}
