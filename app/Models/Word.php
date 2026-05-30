<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    protected $fillable = [
        'sentence_id',
        'text',
        'start_time',
        'end_time'
    ];
}