<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollments extends Model
{
    protected $table = 'enrollments';
    protected $fillable = [
        'user_id',
        'vidio_path',
        'status',
        'catatan_admin',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
