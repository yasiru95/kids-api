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

    // public $tries = 2;

    // public $timeout = 900;

    // public $backoff = [30, 60];

    protected array $validated;

    public function __construct(array $validated)
    {
        $this->validated = $validated;
    }

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
        | 1. Check temporary file
        |--------------------------------------------------------------------------
        */

        if (!$filePath) {
            throw new \Exception(
                'Temporary story file path is missing.'
            );
        }

        if (!Storage::disk('local')->exists($filePath)) {
            throw new \Exception(
                "Temporary story file not found: {$filePath}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Read story TXT
        |--------------------------------------------------------------------------
        */

        $storyText = Storage::disk('local')->get($filePath);

        if (trim($storyText) === '') {
            throw new \Exception(
                'Temporary story file is empty.'
            );
        }

        Log::info('Temporary story file read successfully', [
            'import_id' => $importId,
            'characters' => strlen($storyText),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3. Prepare ProcessStoryJob payload
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
        | 4. Dispatch ProcessStoryJob
        |--------------------------------------------------------------------------
        */

        ProcessStoryJob::dispatch(
            $processPayload
        );

        Log::info('ProcessStoryJob dispatched', [
            'import_id' => $importId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. Delete temporary TXT
        |--------------------------------------------------------------------------
        |
        | The story text has now been passed to ProcessStoryJob.
        |
        */

        Storage::disk('local')->delete(
            $filePath
        );

        Log::info('Temporary story file deleted', [
            'import_id' => $importId,
            'file_path' => $filePath,
        ]);
    }

    /**
     * Handle permanent job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('GenerateStoryJob failed', [
            'import_id' => $this->validated['import_id'] ?? null,
            'title' => $this->validated['title'] ?? null,
            'file_path' => $this->validated['file_path'] ?? null,
            'message' => $exception->getMessage(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Do NOT delete the temporary file here.
        |--------------------------------------------------------------------------
        |
        | If GenerateStoryJob fails before ProcessStoryJob is dispatched,
        | the temporary TXT can still be inspected/recovered.
        |
        */
    }
}