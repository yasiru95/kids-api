<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\Sentence;
use App\Models\Word;

class StoryImportController extends Controller
{
    public function import(Request $request)
    {
        $success_msg = [];
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */


        if (!$request->isJson()) {

            return response()->json([
                'success' => false,
                'message' => 'Request must be JSON'
            ], 400);
        }

        $stories = $request->all();

        if (empty($stories)) {

            return response()->json([
                'success' => false,
                'message' => 'JSON data is empty'
            ], 422);
        }

        if (!is_array($stories)) {

            return response()->json([
                'success' => false,
                'message' => 'JSON must be an array'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | IMPORT STORIES
        |--------------------------------------------------------------------------
        */

        

        foreach ($stories as $storyIndex => $storyData) {

            /*
            |--------------------------------------------------------------------------
            | STORY VALIDATION
            |--------------------------------------------------------------------------
            */

            /*
            |--------------------------------------------------------------------------
            | CHECK STORY EXISTS
            |--------------------------------------------------------------------------
            */

            $storyExists = Story::where('title', $storyData['title'])
                ->exists();

            if ($storyExists) {

                return response()->json([
                    'success' => false,
                    'message' => "Story already exists: {$storyData['title']}"
                ], 409);
            }

            if (
                empty($storyData['title']) ||
                empty($storyData['description']) ||
                empty($storyData['image'])
            ) {

                return response()->json([
                    'success' => false,
                    'message' => "Story data missing at index {$storyIndex}"
                ], 422);
            }

            if (
                !isset($storyData['pages']) ||
                !is_array($storyData['pages']) ||
                count($storyData['pages']) === 0
            ) {

                return response()->json([
                    'success' => false,
                    'message' => "Pages missing in story: {$storyData['title']}"
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE STORY
            |--------------------------------------------------------------------------
            */

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Stories data validated successfully',
        //     'data' => $storyData
        // ]);

            $story = Story::create([
                'title' => $storyData['title'],
                'description' => $storyData['description'],
                'image' => $storyData['image'],

                'category' => $storyData['category'] ?? 'Animals',
                'age_groups' => $storyData['age_groups'] ?? '3+',
                'free' => $storyData['free'] ?? true,
            ]);

            if ($story !== null) {

            $success_msg = [
                'success' => true,
                'message' => "Story '{$story->title}' imported successfully"
            ];

    
            }

        

            

            /*
            |--------------------------------------------------------------------------
            | PAGES
            |--------------------------------------------------------------------------
            */

            foreach ($storyData['pages'] as $pageIndex => $pageData) {

                if (
                    empty($pageData['img']) ||
                    empty($pageData['audio'])
                ) {

                    return response()->json([
                        'success' => false,
                        'message' => "Page image/audio missing in story: {$storyData['title']}"
                    ], 422);
                }

                

                $page = StoryPage::create([
                    'story_id' => $story->id,
                    'page_number' => $pageIndex + 1,
                    'image' => $pageData['img'],
                    'audio' => $pageData['audio'],
                ]);

   

             

                if ($page !== null) {

                    $success_msg = [
                        'success' => true,
                        'message' => "Page {$page->page_number} imported successfully for story '{$story->title}'"
                    ];

                
                }

                /*
                |--------------------------------------------------------------------------
                | SENTENCES
                |--------------------------------------------------------------------------
                */

                foreach ($pageData['sentences'] as $sentenceIndex => $sentenceData) {

                    if (empty($sentenceData['text'])) {

                        return response()->json([
                            'success' => false,
                            'message' => "Sentence text missing"
                        ], 422);
                    }

                    $sentence = Sentence::create([
                        'story_page_id' => $page->id,
                        'text' => $sentenceData['text'],
                    ]);

                    if ($sentence !== null) {

                        $success_msg = [
                            'success' => true,
                            'message' => "Sentence {$sentence->id} imported successfully for page {$page->page_number} in story '{$story->title}'"
                        ];

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | WORDS
                    |--------------------------------------------------------------------------
                    */

                    foreach ($sentenceData['words'] as $wordIndex => $wordData) {

                        if (
                            empty($wordData['text']) ||
                            !isset($wordData['start']) ||
                            !isset($wordData['end'])
                        ) {

                            return response()->json([
                                'success' => false,
                                'message' => "Word data missing"
                            ], 422);
                        }

                        Word::create([
                            'sentence_id' => $sentence->id,
                            'text' => $wordData['text'],

                            // milliseconds
                            'start_time' => intval($wordData['start'] * 1000),
                            'end_time' => intval($wordData['end'] * 1000),
                        ]);

                        if ($wordData !== null) {

                            $success_msg = [
                                'success' => true,
                                'message' => "Word '{$wordData['text']}' imported successfully for sentence {$sentence->id} in page {$page->page_number} of story '{$story->title}'"
                            ];

                        }
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Stories Imported Successfully'
        ]);
    }
}