<?php

namespace App\Jobs;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\Sentence;
use App\Models\Word;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FinalizeStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public string $importId,
        public string $title,
        public string $description,
        public int $pageCount
    ) {
    }

    public function handle(): void
    {
        $slug = Str::slug($this->title);

        Log::info('FinalizeStoryJob started', [
            'import_id' => $this->importId,
            'title' => $this->title,
            'pages' => $this->pageCount,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1. Read all page JSON files
        |--------------------------------------------------------------------------
        */

        $pages = [];

        for ($pageNumber = 1; $pageNumber <= $this->pageCount; $pageNumber++) {

            $pagePath =
                "temp/story-imports/{$this->importId}/page-{$pageNumber}.json";

            if (!Storage::disk('s3')->exists($pagePath)) {
                throw new \RuntimeException(
                    "Page JSON missing: {$pagePath}"
                );
            }

            $pageJson =
                Storage::disk('s3')->get($pagePath);

            $pageData =
                json_decode($pageJson, true);

            if (!is_array($pageData)) {
                throw new \RuntimeException(
                    "Invalid page JSON: {$pagePath}"
                );
            }

            $pages[] = $pageData;
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Check duplicate story
        |--------------------------------------------------------------------------
        */

        $existingStory = Story::where('title', $this->title)->first();

        if ($existingStory) {

            Log::warning('Story already exists', [
                'title' => $this->title,
                'story_id' => $existingStory->id,
            ]);

            $this->cleanupTemporaryFiles();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Build final JSON
        |--------------------------------------------------------------------------
        */

        $storyData = [
            'title' => $this->title,

            'description' => $this->description,

            'image' =>
                config('story.imgurl') .
                "{$slug}/images/cover",

            'pages' => $pages,
        ];

        /*
        |--------------------------------------------------------------------------
        | 4. Save final story JSON to S3
        |--------------------------------------------------------------------------
        */

        $json = json_encode(
            $storyData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new \RuntimeException(
                'Failed to encode final story JSON.'
            );
        }

        $jsonPath =
            "Stories/{$slug}/{$slug}.json";

        Storage::disk('s3')->put(
            $jsonPath,
            $json,
            [
                'ContentType' => 'application/json',
                'CacheControl' => 'public, max-age=31536000',
            ]
        );

        if (!Storage::disk('s3')->exists($jsonPath)) {
            throw new \RuntimeException(
                "Final JSON upload failed: {$jsonPath}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Create database records
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use ($storyData) {

            /*
            |--------------------------------------------------------------------------
            | STORY
            |--------------------------------------------------------------------------
            */

            $story = Story::create([
                'title' => $storyData['title'],

                'slug' => Str::slug($storyData['title']),

                'description' => $storyData['description'],

                'image' => $storyData['image'],

                'category' => 'Animals',

                'age_groups' => '3+',

                'free' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | PAGE / SENTENCE / WORD DATA
            |--------------------------------------------------------------------------
            */

            foreach ($storyData['pages'] as $pageData) {

                if (
                    empty($pageData['img']) ||
                    empty($pageData['audio'])
                ) {
                    throw new \RuntimeException(
                        "Image or audio missing for page {$pageData['page_number']}"
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | PAGE
                |--------------------------------------------------------------------------
                */

                $page = StoryPage::create([
                    'story_id' => $story->id,

                    'page_number' =>
                        $pageData['page_number'],

                    'image' =>
                        $pageData['img'],

                    'audio' =>
                        $pageData['audio'],
                ]);

                /*
                |--------------------------------------------------------------------------
                | SENTENCES
                |--------------------------------------------------------------------------
                */

                foreach ($pageData['sentences'] as $sentenceData) {

                    if (empty($sentenceData['text'])) {
                        throw new \RuntimeException(
                            "Sentence text missing on page {$page->page_number}"
                        );
                    }

                    $sentence = Sentence::create([
                        'story_page_id' => $page->id,

                        'text' =>
                            $sentenceData['text'],
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | WORDS
                    |--------------------------------------------------------------------------
                    */

                    $wordsToInsert = [];

                    $now = now();

                    foreach ($sentenceData['words'] as $wordData) {

                        if (
                            empty($wordData['text']) ||
                            !isset($wordData['start']) ||
                            !isset($wordData['end'])
                        ) {
                            throw new \RuntimeException(
                                "Invalid word data on page {$page->page_number}"
                            );
                        }

                        $wordsToInsert[] = [
                            'sentence_id' => $sentence->id,

                            'text' =>
                                $wordData['text'],

                            'start_time' =>
                                (int) $wordData['start'],

                            'end_time' =>
                                (int) $wordData['end'],

                            'created_at' => $now,

                            'updated_at' => $now,
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | BULK WORD INSERT
                    |--------------------------------------------------------------------------
                    */

                    if (!empty($wordsToInsert)) {
                        Word::insert($wordsToInsert);
                    }
                }
            }

            Log::info('Story database import completed', [
                'story_id' => $story->id,
                'title' => $story->title,
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | 6. Delete temporary files
        |--------------------------------------------------------------------------
        */

        $this->cleanupTemporaryFiles();

        Log::info('FinalizeStoryJob completed successfully', [
            'import_id' => $this->importId,
            'title' => $this->title,
            'json' => $jsonPath,
        ]);
    }

    private function cleanupTemporaryFiles(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Delete all temporary page JSON files
        |--------------------------------------------------------------------------
        */

        $directory =
            "temp/story-imports/{$this->importId}";

        Storage::disk('s3')->deleteDirectory($directory);

        /*
        |--------------------------------------------------------------------------
        | Delete metadata
        |--------------------------------------------------------------------------
        */

        Storage::disk('s3')->delete(
            "temp/story-imports/{$this->importId}.json"
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('FinalizeStoryJob failed', [
            'import_id' => $this->importId,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
