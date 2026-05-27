<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Http\Resources\StoryResource;
use App\Http\Resources\StoryWordResource;
use App\Http\Resources\StorySentenceResource;
use App\Http\Resources\StoryPageResource;

class StoryController extends Controller
{
    // GET ALL STORIES
    public function index()
    {
        return Story::select(
            'id',
            'title',
            'description',
            'image'
        )->get();
    }

    // GET SINGLE STORY
    public function show($id)
    {
        $story = Story::with([
            'pages.sentences.words'
        ])->findOrFail($id);

        return new StoryResource($story);
    }
}