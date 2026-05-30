<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryPage extends Model
{
    protected $fillable = [
        'story_id',
        'page_number',
        'image',
        'audio'
    ];

    public function sentences()
    {
        return $this->hasMany(Sentence::class);
    }
}