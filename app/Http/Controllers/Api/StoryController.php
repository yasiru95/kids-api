<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Http\Resources\StoryResource;
use App\Http\Resources\StoryWordResource;
use App\Http\Resources\StorySentenceResource;
use App\Http\Resources\StoryPageResource;
use Illuminate\Support\Facades\Log;

class StoryController extends Controller
{
    // GET ALL STORIES
    public function index()
    {
        Log::info('Fetching all stories');
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

     // ✅ Filter stories by category
    public function filterByCategory($category)
    {
        $stories = Story::where('category', $category)
            ->latest()
            ->get();

        return new StoryResource($stories);


        // return response()->json([
        //     'status' => true,
        //     'category' => $category,
        //     'count' => $stories->count(),
        //     'stories' => $stories
        // ]);
    }
}