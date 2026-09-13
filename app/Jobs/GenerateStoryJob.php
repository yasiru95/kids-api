<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateStoryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Retry settings
    |--------------------------------------------------------------------------
    */

    public int $tries = 3;

    public int $timeout = 120;

    protected array $validated;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(array $validated)
    {
        $this->validated = $validated;
    }

    /*
    |--------------------------------------------------------------------------
    | Handle job
    |--------------------------------------------------------------------------
    */

    public function handle(): void
    {
        $importId = $this->validated['import_id'] ?? null;

        $filePath = $this->validated['file_path'] ?? null;

        Log::info('GenerateStoryJob started', [
            'import_id' => $importId,
            'title' => $this->validated['title'] ?? null,
            'file_path' => $filePath,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1. Check file path
        |--------------------------------------------------------------------------
        */

        if (!$filePath) {

            throw new \Exception(
                'Temporary story file path is missing.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Check S3 file
        |--------------------------------------------------------------------------
        */

        $exists = Storage::disk('s3')->exists($filePath);

        Log::info('Temporary S3 file check', [
            'import_id' => $importId,
            'file_path' => $filePath,
            'exists' => $exists,
        ]);

        if (!$exists) {

            throw new \Exception(
                "Temporary story file not found in S3: {$filePath}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Read story from S3
        |--------------------------------------------------------------------------
        */

        $storyText = Storage::disk('s3')->get($filePath);

        if (!$storyText) {

            throw new \Exception(
                'Temporary story file is empty.'
            );
        }

        $storyText = trim($storyText);

        if ($storyText === '') {

            throw new \Exception(
                'Temporary story file contains no text.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Log successful read
        |--------------------------------------------------------------------------
        */

        Log::info('Temporary story file read successfully', [
            'import_id' => $importId,
            'characters' => strlen($storyText),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. Prepare ProcessStoryJob payload
        |--------------------------------------------------------------------------
        */

        $processPayload = [
            'import_id' => $importId,

            'title' => $this->validated['title'],

            'description' => $this->validated['description'],

            'story' => $storyText,
        ];

        /*
        |--------------------------------------------------------------------------
        | 6. Dispatch ProcessStoryJob
        |--------------------------------------------------------------------------
        */

        ProcessStoryJob::dispatch($processPayload);

        Log::info('ProcessStoryJob dispatched', [
            'import_id' => $importId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 7. Delete temporary S3 file
        |--------------------------------------------------------------------------
        */

        try {

            Storage::disk('s3')->delete($filePath);

            Log::info('Temporary S3 story file deleted', [
                'import_id' => $importId,
                'file_path' => $filePath,
            ]);

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Do not fail the story because temporary cleanup failed.
            |--------------------------------------------------------------------------
            */

            Log::warning(
                'Could not delete temporary story file',
                [
                    'import_id' => $importId,
                    'file_path' => $filePath,
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Failed job
    |--------------------------------------------------------------------------
    */

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateStoryJob failed', [

            'import_id' =>
                $this->validated['import_id'] ?? null,

            'title' =>
                $this->validated['title'] ?? null,

            'file_path' =>
                $this->validated['file_path'] ?? null,

            'message' =>
                $exception->getMessage(),

            'trace' =>
                $exception->getTraceAsString(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Do NOT delete the temporary S3 file here.
        |
        | Keeping it allows you to inspect/retry/debug the failed story.
        |
        |--------------------------------------------------------------------------
        */
    }
}