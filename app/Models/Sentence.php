<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sentence extends Model
{
    protected $fillable = [
        'story_page_id',
        'text'
    ];

  

    // ✅ Sentence → Words
    public function words()
    {
        return $this->hasMany(Word::class);
    }
}