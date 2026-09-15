<?php

namespace App\Jobs;

use Aws\Polly\PollyClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessStoryPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(
        public string $importId,
        public string $title,
        public int $pageNumber,
        public array $lines
    ) {
    }

    public function handle(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Stop if batch was cancelled
        |--------------------------------------------------------------------------
        */

        if ($this->batch()?->cancelled()) {
            Log::warning('Page job cancelled', [
                'import_id' => $this->importId,
                'page' => $this->pageNumber,
            ]);

            return;
        }

        $slug = \Illuminate\Support\Str::slug($this->title);

        Log::info('Processing story page', [
            'import_id' => $this->importId,
            'page' => $this->pageNumber,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Polly client
        |--------------------------------------------------------------------------
        */

        $polly = new PollyClient([
            'region' => config('services.aws.region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Page text
        |--------------------------------------------------------------------------
        */

        $pageText = implode(' ', $this->lines);

        if (empty(trim($pageText))) {
            throw new \RuntimeException(
                "Page {$this->pageNumber} has empty text."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Generate MP3
        |--------------------------------------------------------------------------
        */

        $audioResult = $polly->synthesizeSpeech([
            'Text' => $pageText,
            'OutputFormat' => 'mp3',
            'VoiceId' => 'Ruth',
            'Engine' => 'long-form',
        ]);

        $audioContent = $audioResult['AudioStream']->getContents();

        if (empty($audioContent)) {
            throw new \RuntimeException(
                "Polly returned empty audio for page {$this->pageNumber}."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Upload MP3 to S3
        |--------------------------------------------------------------------------
        */

        $audioPath =
            "Stories/{$slug}/audio/page-{$this->pageNumber}.mp3";

        Storage::disk('s3')->put(
            $audioPath,
            $audioContent,
            [
                'ContentType' => 'audio/mpeg',
                'CacheControl' => 'public, max-age=31536000',
            ]
        );

        if (!Storage::disk('s3')->exists($audioPath)) {
            throw new \RuntimeException(
                "Audio upload failed: {$audioPath}"
            );
        }

        $audioUrl =
            "https://kidsstoryflix-images.s3.us-east-1.amazonaws.com/{$audioPath}";

        /*
        |--------------------------------------------------------------------------
        | 3. Generate Polly speech marks
        |--------------------------------------------------------------------------
        */

        $speechResult = $polly->synthesizeSpeech([
            'Text' => $pageText,
            'OutputFormat' => 'json',
            'VoiceId' => 'Ruth',
            'Engine' => 'long-form',
            'SpeechMarkTypes' => ['word'],
        ]);

        $speechContent =
            $speechResult['AudioStream']->getContents();

        if (empty($speechContent)) {
            throw new \RuntimeException(
                "Polly returned empty speech marks for page {$this->pageNumber}."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Parse speech marks
        |--------------------------------------------------------------------------
        */

        $speechLines = explode(
            "\n",
            trim($speechContent)
        );

        $allWords = [];

        foreach ($speechLines as $line) {

            if (empty(trim($line))) {
                continue;
            }

            $json = json_decode($line, true);

            if (
                is_array($json) &&
                isset($json['value']) &&
                isset($json['time'])
            ) {
                $allWords[] = [
                    'text' => $json['value'],
                    'time' => (int) $json['time'],
                ];
            }
        }

        if (empty($allWords)) {
            throw new \RuntimeException(
                "No speech marks generated for page {$this->pageNumber}."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Build sentences + words
        |--------------------------------------------------------------------------
        */

        $sentences = [];

        $wordIndex = 0;

        foreach ($this->lines as $sentenceText) {

            $sentenceText = trim($sentenceText);

            if ($sentenceText === '') {
                continue;
            }

            $sentenceWords = preg_split(
                '/\s+/',
                $sentenceText
            );

            $words = [];

            foreach ($sentenceWords as $word) {

                $word = trim($word);

                if ($word === '') {
                    continue;
                }

                if (!isset($allWords[$wordIndex])) {
                    break;
                }

                $current = $allWords[$wordIndex];

                $next =
                    $allWords[$wordIndex + 1] ?? null;

                $start = (int) $current['time'];

                $end = $next
                    ? (int) $next['time']
                    : $start + 500;

                $words[] = [
                    'text' => $current['text'],
                    'start' => $start,
                    'end' => $end,
                ];

                $wordIndex++;
            }

            $sentences[] = [
                'text' => $sentenceText,
                'words' => $words,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Build page JSON
        |--------------------------------------------------------------------------
        */

        $imageUrl =
            config('story.imgurl') .
            "{$slug}/images/page-{$this->pageNumber}";

        $pageData = [
            'page_number' => $this->pageNumber,
            'text' => $pageText,

            'img' => $imageUrl,

            'audio' => $audioUrl,

            'sentences' => $sentences,
        ];

        /*
        |--------------------------------------------------------------------------
        | 7. Save temporary page JSON
        |--------------------------------------------------------------------------
        */

        $pageJsonPath =
            "temp/story-imports/{$this->importId}/page-{$this->pageNumber}.json";

        Storage::disk('s3')->put(
            $pageJsonPath,
            json_encode(
                $pageData,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
            [
                'ContentType' => 'application/json',
            ]
        );

        Log::info('Story page completed', [
            'import_id' => $this->importId,
            'page' => $this->pageNumber,
            'audio' => $audioUrl,
            'words' => count($allWords),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessStoryPageJob failed', [
            'import_id' => $this->importId,
            'title' => $this->title,
            'page' => $this->pageNumber,
            'error' => $exception->getMessage(),
        ]);
    }
}
