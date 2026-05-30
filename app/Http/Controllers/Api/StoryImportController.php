<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\Sentence;
use App\Models\Word;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Aws\Polly\PollyClient;
use App\Services\PollyService;

class StoryImportController extends Controller
{

 public function generateStoryJSON(Request $request)
    {
        
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'story' => 'required|string|min:10',
        ]);

        /*
        |--------------------------------------------------------------------------
        | AWS POLLY CLIENT
        |--------------------------------------------------------------------------
        */

        $polly = new PollyClient([
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => 'latest',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ]
        ]);

        /*
        |--------------------------------------------------------------------------
        | STORY SETUP
        |--------------------------------------------------------------------------
        */

        $slug = Str::slug($validated['title']);

        // Split story by lines
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $validated['story']))
        ));

        // Every 3 lines = 1 page
        $chunks = array_chunk($lines, 3);

        /*
        |--------------------------------------------------------------------------
        | MAIN STORY JSON
        |--------------------------------------------------------------------------
        */

        $story = [
            'id' => 1,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'image' => 'https://sample-image-url.com/' . $slug . '-cover.webp',
            'pages' => []
        ];

        /*
        |--------------------------------------------------------------------------
        | AUDIO FOLDER
        |--------------------------------------------------------------------------
        */

        $folder = public_path("audio/$slug");

        if (!File::exists($folder)) {
            File::makeDirectory($folder, 0755, true);
        }

        /*
        |--------------------------------------------------------------------------
        | LOOP PAGES
        |--------------------------------------------------------------------------
        */

        foreach ($chunks as $pageIndex => $chunk) {

            $pageNumber = $pageIndex + 1;

            /*
            |--------------------------------------------------------------------------
            | PAGE TEXT
            |--------------------------------------------------------------------------
            */

            $pageText = implode(" ", $chunk);

            /*
            |--------------------------------------------------------------------------
            | GENERATE AUDIO MP3
            |--------------------------------------------------------------------------
            */

            $audioResult = $polly->synthesizeSpeech([
                'Text' => $pageText,
                'OutputFormat' => 'mp3',
                'VoiceId' => 'Ruth',
                'Engine' => 'long-form',
            ]);

            /*
            |--------------------------------------------------------------------------
            | SAVE AUDIO
            |--------------------------------------------------------------------------
            */

            $audioFile = "$slug-page-$pageNumber.mp3";

            file_put_contents(
                "$folder/$audioFile",
                $audioResult['AudioStream']->getContents()
            );

            /*
            |--------------------------------------------------------------------------
            | GENERATE SPEECH MARKS
            |--------------------------------------------------------------------------
            */

            $speechMarkResult = $polly->synthesizeSpeech([
                'Text' => $pageText,
                'OutputFormat' => 'json',
                'VoiceId' => 'Ruth',
                'Engine' => 'long-form',
                'SpeechMarkTypes' => ['word']
            ]);

            /*
            |--------------------------------------------------------------------------
            | GET SPEECH MARK DATA
            |--------------------------------------------------------------------------
            */

            $speechData = $speechMarkResult['AudioStream']->getContents();

            $speechLines = explode("\n", trim($speechData));

            $allWords = [];

            foreach ($speechLines as $line) {

                $json = json_decode($line, true);

                if (!$json || !isset($json['value'])) {
                    continue;
                }

                $allWords[] = [
                    'text' => $json['value'],
                    'time' => $json['time']
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | BUILD SENTENCES
            |--------------------------------------------------------------------------
            */

            $sentences = [];

            $wordIndex = 0;

            foreach ($chunk as $sentenceText) {

                $sentenceWords = preg_split('/\s+/', trim($sentenceText));

                $words = [];

                foreach ($sentenceWords as $i => $wordText) {

                    if (!isset($allWords[$wordIndex])) {
                        continue;
                    }

                    $currentWord = $allWords[$wordIndex];

                    $nextWord = $allWords[$wordIndex + 1] ?? null;

                    // Start time
                    $start = round($currentWord['time'] / 1000, 3);

                    // End time
                    if ($nextWord) {
                        $end = round($nextWord['time'] / 1000, 3);
                    } else {
                        $end = round(($currentWord['time'] + 500) / 1000, 3);
                    }

                    $words[] = [
                        'text' => $currentWord['text'],
                        'start' => $start,
                        'end' => $end,
                    ];

                    $wordIndex++;
                }

                $sentences[] = [
                    'text' => $sentenceText,
                    'words' => $words
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | ADD PAGE
            |--------------------------------------------------------------------------
            */

            $story['pages'][] = [
                'img' => 'https://sample-image-url.com/' . $slug . '-page-' . $pageNumber . '.webp',
                'audio' => url("audio/$slug/$audioFile"),
                'sentences' => $sentences
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE JSON FILE
        |--------------------------------------------------------------------------
        */

        file_put_contents(
            public_path("audio/$slug/$slug.story.json"),
            json_encode([$story], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'story' => $story,
            'json_url' => url("audio/$slug/$slug.story.json")
        ]);
    }

    












//......................................................................
   var $validated=null;
    //create_story_json
    public function create_story_json(Request $request){

         // ✅ 2. Validation with proper error handling
        try {
            $this->validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'story' => 'required|string|min:10',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        }

        $result = $this->amazon_polly();
        return response()->json([
            'success' => true,
            'message' => 'Story processed successfully',
            'data' => $result
        ]);


        // return $this->splitStoryByLines($this->validated['story']);


    

    }

   public function splitStoryByLines($story, $linesPerChunk = 3)
    {
    //convert story to array of lines and trim whitespace
    $lines = array_values(array_filter(array_map('trim', explode("\n", $story)))); 
    //3lines to array chunk
    $story_array = array_chunk($lines, $linesPerChunk);

    $this->amazon_polly();
 

    foreach ($story_array as $index => $part) {
    // 3 line string

    $result = collect($part)
    ->map(fn($line) => '"' . $line . '"')
    ->implode("\n");
    $this->create_audio($result, $index + 1);

    }

    return ;

    }

    public function amazon_polly(){
     // Create AWS Polly client
        $polly = new PollyClient([
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => 'latest',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ]
        ]);

        $slug = Str::slug($this->validated['title']);

        // Split story into lines (3-line chunks)
        $lines = array_values(array_filter(explode("\n", $this->validated['story'])));
        $chunks = array_chunk($lines, 3);

        $results = [];

        foreach ($chunks as $index => $chunk) {

            // convert 3 lines → sentence
            $text = implode(" ", $chunk);

            $marks = $this->generateSpeechMarks($text);
            return response()->json([
                'success' => true,
                'marks' => $marks
            ]);

            // $result = $polly->synthesizeSpeech([
            //     'Text' => $text,
            //     'OutputFormat' => 'mp3',
            //     'VoiceId' => 'Ruth',
            //     'Engine' => 'long-form', 
            
            // ]);

            // $audioStream = $result['AudioStream'];

            // // folder
            // $path = public_path("audio/{$slug}");

            // if (!File::exists($path)) {
            //     File::makeDirectory($path, 0755, true);
            // }

            // $fileName = $slug . '-page-' . ($index + 1) . '.mp3';

            // file_put_contents($path . '/' . $fileName, $audioStream);

            // $results[] = [
            //     'page' => $index + 1,
            //     'text' => $text,
            //     'audio_url' => url("audio/{$slug}/{$fileName}")
            // ];
        }

        // return response()->json([
        //     'success' => true,
        //     'title' => $this->validated['title'],
        //     'data' => $results
        // ]);
    

    }


public function generateSpeechMarks(string $text)
{
    $polly = new PollyService();
    $marks = $polly->synthesizeWithMarks($text);

    return response()->json([
        'xxxxx' => true,
        'marks' => $marks
    ]);
}

    public function create_audio( $storySentence, int $page){
    {
    // create SEO slug
    $slug = Str::slug($this->validated['title']);

    $response = Http::withoutVerifying()->get(
    'https://api.voicerss.org/',
    [

    'key' => env('VOICERSS_API_KEY'),
    'hl'  => 'en-gb',
    'v' => 'Alice',
    'src' => $storySentence,

    ]
    );

    if ($response->successful()) {

    // create folder if not exists
    if (!File::exists(public_path('audio'))) {
    File::makeDirectory(public_path('audio'), 0755, true);
    }

    $filename = $slug . '-page-' . $page . '.wav' ;

    file_put_contents(
    public_path('audio/' . $filename),
    $response->body()
    );

    return response()->json([
    'success' => true,
    'message' => 'Audio generated successfully',
    'audio_url' => url('audio/' . $filename)
    ]);
    }

    return response()->json([
    'success' => false,
    'message' => 'Failed to generate audio'
    ], 500);
    }

    }



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