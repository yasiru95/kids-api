<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Http\Resources\StoryResource;
use App\Http\Resources\StoryWordResource;
use App\Http\Resources\StorySentenceResource;
use App\Http\Resources\StoryPageResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoryController extends Controller
{
    // GET ALL STORIES
    public function index()
    {
        Log::info('Fetching all stories');
        return Story::select(
            'id',
            'title',
            'slug',
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

        // GET SINGLE STORY Slug
    public function showSlug($slug)
    {
        $story = Story::with([
            'pages.sentences.words'
        ])->where('slug', $slug)->firstOrFail();

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


    public function destroy(int $id)
    {
        try {

            $story = Story::find($id);

            if (!$story) {
                return response()->json([
                    'success' => false,
                    'message' => 'Story not found.',
                ], 404);
            }

            $storyTitle = $story->title;
            $slug = $story->slug ?: Str::slug($story->title);

            /*
             * Delete database records.
             */
            DB::transaction(function () use ($story) {

                foreach ($story->pages as $page) {

                    /*
                     * Delete words.
                     */
                    $page->words()->delete();

                    /*
                     * Delete sentences.
                     */
                    $page->sentences()->delete();

                    /*
                     * Delete page.
                     */
                    $page->delete();
                }

                /*
                 * Delete story.
                 */
                $story->delete();
            });

            /*
             * Delete all story files from S3.
             *
             * Stories/{slug}/
             */
            $s3Path = "Stories/{$slug}";

            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->deleteDirectory($s3Path);
            }

            return response()->json([
                'success' => true,
                'message' => 'Story deleted successfully.',
                'story_id' => $id,
                'title' => $storyTitle,
                'slug' => $slug,
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete story.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}