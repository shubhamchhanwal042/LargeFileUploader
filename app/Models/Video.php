<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'progress',
        'status'
    ];
}