<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Aws\Polly\PollyClient;
use Illuminate\Support\Str;
use App\Models\Story;
use App\Models\StoryPage;
use App\Models\Sentence;
use App\Models\Word;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;

class ProcessStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $validated;

    public function __construct($validated)
    {
        $this->validated = $validated;
    }

    public function handle()
    {
        $title = $this->validated['title'];
        $description = $this->validated['description'];
        $storyText = $this->validated['story'];

            $storyExists = Story::where('title', $title)
                ->exists();

            if ($storyExists) {

            Log::warning("Story already exists: $title");

            }else{
                $slug = Str::slug($title);


        $polly = new PollyClient([
            'region' => config('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('AWS_ACCESS_KEY_ID'),
                'secret' => config('AWS_SECRET_ACCESS_KEY'),
            ]
        ]);

        // Split story
        $lines = array_values(array_filter(array_map('trim', explode("\n", $storyText))));
        $chunks = array_chunk($lines, 3);

        $storyData = [
            'title' => $title,
            'description' => $description,
            'image' => config('story.imgurl') . "{$slug}/images/{$slug}-cover.webp",
            'pages' => []
        ];

        foreach ($chunks as $pageIndex => $chunk) {

            $pageNumber = $pageIndex + 1;
            $pageText = implode(" ", $chunk);

            /*
            |-----------------------------------
            | 1. AUDIO GENERATION
            |-----------------------------------
            */
            $audioResult = $polly->synthesizeSpeech([
                'Text' => $pageText,
                'OutputFormat' => 'mp3',
                'VoiceId' => 'Ruth',
                'Engine' => 'long-form',
            ]);

            $audioContent = $audioResult['AudioStream']->getContents();

            $audioUpload = Cloudinary::uploadApi()->upload(
                'data:audio/mp3;base64,' . base64_encode($audioContent),
                [
                    'resource_type' => 'video',
                    'folder' => "stories/{$slug}/audio",
                    'public_id' => "{$slug}-page-{$pageNumber}",
                    'overwrite' => true,
                ]
            );

            $audioUrl = $audioUpload['secure_url'];

            // Log::info("Generated audio for page {$pageNumber}", ['audio_url' => $audioUrl]);
      

            /*
            |-----------------------------------
            | 2. SPEECH MARKS
            |-----------------------------------
            */
            $speech = $polly->synthesizeSpeech([
                'Text' => $pageText,
                'OutputFormat' => 'json',
                'VoiceId' => 'Ruth',
                'Engine' => 'long-form',
                'SpeechMarkTypes' => ['word']
            ]);

            $speechLines = explode("\n", trim($speech['AudioStream']->getContents()));

            $allWords = [];

            foreach ($speechLines as $line) {
                $json = json_decode($line, true);

                if (isset($json['value'])) {
                    $allWords[] = [
                        'text' => $json['value'],
                        'time' => $json['time']
                    ];
                }
            }

            /*
            |-----------------------------------
            | 3. CREATE PAGE STRUCTURE
            |-----------------------------------
            */
            $sentences = [];
            $wordIndex = 0;

            foreach ($chunk as $sentenceText) {

                $sentenceWords = preg_split('/\s+/', trim($sentenceText));
                $words = [];

                foreach ($sentenceWords as $w) {

                    if (!isset($allWords[$wordIndex])) continue;

                    $current = $allWords[$wordIndex];
                    $next = $allWords[$wordIndex + 1] ?? null;

                    $words[] = [
                        'text' => $current['text'],
                        'start' => round($current['time'] / 1000, 3),
                        'end' => round(($next['time'] ?? ($current['time'] + 500)) / 1000, 3),
                    ];

                    $wordIndex++;
                }

                $sentences[] = [
                    'text' => $sentenceText,
                    'words' => $words
                ];
            }

            $storyData['pages'][] = [
                'img' => config('story.imgurl') . "{$slug}/images/{$slug}-page-{$pageNumber}.webp",
                'audio' => $audioUrl,
                'sentences' => $sentences
            ];
        }

        /*
        |-----------------------------------
        | 4. SAVE TO DB
        |-----------------------------------
        */
        Log::info('Story data generated, starting DB import', ['title' => $title]);

        $finalResult = $this->importStory([$storyData]);

        /*
        |--------------------------------------------------------------------------
        | SAVE JSON FILE
        |--------------------------------------------------------------------------
        */
        $json = json_encode(
        $storyData,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        Log::info('Generated JSON for story', ['json_length' => strlen($json)]);

        
       try{
         $uploaded = Cloudinary::uploadApi()->upload(
        'data:application/json;base64,' . base64_encode($json),
        // json_encode([$story], JSON_PRETTY_PRINT ),
        [
        'resource_type' => 'raw',
        'folder' => "stories/{$slug}",
        'public_id' => "{$slug}.json",
        'format' => 'json',
        'overwrite' => true,
        ]
        );
       }catch(\Throwable $th){
        return response()->json([
            'success' => false,
            'message' => 'JSON upload failed: ' . $th->getMessage()
        ], 500);
       }
         Log::info('JSON file uploaded', [
          'url' => $uploaded['secure_url']
         ]);
            }

        
    }

    private function importStory($stories)

    {
        
        $success_msg = [];

   

        foreach ($stories as $storyIndex => $storyData) {

           

            $storyExists = Story::where('title', $storyData['title'])
                ->exists();

            if ($storyExists) {
                Log::warning("Story already exists: {$storyData['title']}");
            }else{
                
            /*
            |--------------------------------------------------------------------------
            | CREATE STORY
            |--------------------------------------------------------------------------
            */

  

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

        

            Log::info("NEW story created with ID: {$story->id} for story: {$storyData['title']}", $success_msg);


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

                // Log::info("Page {$page->page_number}");

   

             

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
        }

             

            
            


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */
        Log::info("Story import completed for story: {$storyData['title']}", $success_msg);
    
     }
 }
