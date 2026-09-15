<?php

namespace App\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $timeout = 120;

    public function __construct(
        public string $importId,
        public string $title,
        public string $description,
        public string $storyPath
    ) {
    }

    public function handle(): void
    {
        Log::info('GenerateStoryJob started', [
            'import_id' => $this->importId,
            'title' => $this->title,
            'story_path' => $this->storyPath,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1. Check temporary TXT exists
        |--------------------------------------------------------------------------
        */

        if (!Storage::disk('s3')->exists($this->storyPath)) {
            throw new \RuntimeException(
                "Temporary story file not found: {$this->storyPath}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Read story TXT from S3
        |--------------------------------------------------------------------------
        */

        $storyText = Storage::disk('s3')->get($this->storyPath);

        if (empty(trim($storyText))) {
            throw new \RuntimeException('Story file is empty.');
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Split story into lines
        |--------------------------------------------------------------------------
        */

        $lines = preg_split(
            '/\r\n|\r|\n/',
            $storyText
        );

        $lines = array_values(
            array_filter(
                array_map('trim', $lines),
                fn ($line) => $line !== ''
            )
        );

        if (empty($lines)) {
            throw new \RuntimeException('No valid story lines found.');
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Three lines = one page
        |--------------------------------------------------------------------------
        */

        $chunks = array_chunk($lines, 3);

        Log::info('Story split into pages', [
            'import_id' => $this->importId,
            'pages' => count($chunks),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. Create one ProcessStoryPageJob per page
        |--------------------------------------------------------------------------
        */

        $pageJobs = [];

        foreach ($chunks as $pageIndex => $chunk) {

            $pageNumber = $pageIndex + 1;

            $pageJobs[] = new ProcessStoryPageJob(
                importId: $this->importId,
                title: $this->title,
                pageNumber: $pageNumber,
                lines: $chunk
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Create Laravel batch
        |--------------------------------------------------------------------------
        */

        $importId = $this->importId;
        $title = $this->title;
        $description = $this->description;

        $batch = Bus::batch($pageJobs)
            ->name("Story: {$title}")

            /*
            |--------------------------------------------------------------------------
            | All page jobs completed successfully
            |--------------------------------------------------------------------------
            */

            ->then(function (Batch $batch) use (
                $importId,
                $title,
                $description
            ) {

                Log::info('All story page jobs completed', [
                    'import_id' => $importId,
                    'batch_id' => $batch->id,
                    'total_jobs' => $batch->totalJobs,
                ]);

                FinalizeStoryJob::dispatch(
                    importId: $importId,
                    title: $title,
                    description: $description,
                    pageCount: $batch->totalJobs
                );
            })

            /*
            |--------------------------------------------------------------------------
            | Batch failed
            |--------------------------------------------------------------------------
            */

            ->catch(function (
                Batch $batch,
                Throwable $e
            ) use ($importId) {

                Log::error('Story batch failed', [
                    'import_id' => $importId,
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            })

            /*
            |--------------------------------------------------------------------------
            | Batch finished
            |--------------------------------------------------------------------------
            */

            ->finally(function (Batch $batch) use ($importId) {

                Log::info('Story batch finished', [
                    'import_id' => $importId,
                    'batch_id' => $batch->id,
                    'processed_jobs' => $batch->processedJobs(),
                    'failed_jobs' => $batch->failedJobs,
                ]);
            })

            ->dispatch();

        /*
        |--------------------------------------------------------------------------
        | 7. Save batch metadata
        |--------------------------------------------------------------------------
        */

        $metadata = [
            'import_id' => $this->importId,
            'batch_id' => $batch->id,
            'title' => $this->title,
            'description' => $this->description,
            'page_count' => count($chunks),
            'status' => 'processing',
        ];

        $metadataPath =
            "temp/story-imports/{$this->importId}.json";

        Storage::disk('s3')->put(
            $metadataPath,
            json_encode(
                $metadata,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
            [
                'ContentType' => 'application/json',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 8. Delete temporary TXT
        |--------------------------------------------------------------------------
        */

        Storage::disk('s3')->delete($this->storyPath);

        /*
        |--------------------------------------------------------------------------
        | 9. Finished
        |--------------------------------------------------------------------------
        */

        Log::info('GenerateStoryJob completed', [
            'import_id' => $this->importId,
            'batch_id' => $batch->id,
            'pages' => count($chunks),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Job failed completely
    |--------------------------------------------------------------------------
    */

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateStoryJob failed', [
            'import_id' => $this->importId,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}