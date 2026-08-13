<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Embedding extends Model 
{
    protected $table = 'embedding';
    protected $fillable = [
        'user_id',
        'face_embeddings'
    ];
}