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
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use App\Jobs\GenerateStoryJob;
use App\Jobs\ProcessStoryJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoryImportController extends Controller
{
    public function generateStoryJSON(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Validate request
        |--------------------------------------------------------------------------
        */

        try {

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'storyFile' => 'required|file|mimes:txt|max:5120',
            ]);

        } catch (ValidationException $e) {

            Log::error('Story validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Get uploaded file
        |--------------------------------------------------------------------------
        */

        try {

            $file = $request->file('storyFile');

            if (!$file) {

                return response()->json([
                    'success' => false,
                    'message' => 'storyFile is missing in request',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Read story text
            |--------------------------------------------------------------------------
            */

            $storyText = file_get_contents(
                $file->getRealPath()
            );

            if ($storyText === false) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to read story file.',
                ], 422);
            }

            $storyText = trim($storyText);

            if ($storyText === '') {

                return response()->json([
                    'success' => false,
                    'message' => 'Story file is empty.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Create unique import ID
            |--------------------------------------------------------------------------
            */

            $importId = (string) Str::uuid();

            /*
            |--------------------------------------------------------------------------
            | 5. Temporary S3 file
            |--------------------------------------------------------------------------
            |
            | This file is temporary.
            |
            | Example:
            |
            | temp/story-imports/uuid.txt
            |
            |--------------------------------------------------------------------------
            */

            $filePath = "temp/story-imports/{$importId}.txt";

            /*
            |--------------------------------------------------------------------------
            | 6. Save story text to S3
            |--------------------------------------------------------------------------
            */

            $saved = Storage::disk('s3')->put(
                $filePath,
                $storyText,
                [
                    'ContentType' => 'text/plain',
                ]
            );

            if (!$saved) {

                Log::error('Failed to save temporary story file to S3', [
                    'import_id' => $importId,
                    'file_path' => $filePath,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save story file.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Verify S3 file exists
            |--------------------------------------------------------------------------
            */

            $exists = Storage::disk('s3')->exists($filePath);

            if (!$exists) {

                Log::error('Temporary story file was not found after upload', [
                    'import_id' => $importId,
                    'file_path' => $filePath,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Temporary story file could not be verified.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | 8. Log successful upload
            |--------------------------------------------------------------------------
            */

            Log::info('Temporary story uploaded to S3', [
                'import_id' => $importId,
                'file_path' => $filePath,
                'size' => strlen($storyText),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 9. Queue payload
            |--------------------------------------------------------------------------
            |
            | We DO NOT put the complete story text into the queue.
            |
            | We only send the S3 path.
            |--------------------------------------------------------------------------
            */

            $payload = [
                'import_id' => $importId,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'file_path' => $filePath,
            ];

            /*
            |--------------------------------------------------------------------------
            | 10. Dispatch queue job
            |--------------------------------------------------------------------------
            */

            GenerateStoryJob::dispatch($payload);

            Log::info('GenerateStoryJob dispatched', [
                'import_id' => $importId,
                'file_path' => $filePath,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 11. Return immediately
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Story processing started.',
                'import_id' => $importId,
                'status' => 'processing',
            ], 202);

        } catch (\Throwable $e) {

            Log::error('Failed to start story processing', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start story processing.',
            ], 500);
        }
    }
}