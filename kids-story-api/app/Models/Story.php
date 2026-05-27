<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image',
        'category',
        'age_group',
        'is_free'
    ];

    public function pages()
    {
        return $this->hasMany(StoryPage::class);
    }
}