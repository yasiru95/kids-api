<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use App\Jobs\GenerateStoryJob;

class StoryImportController extends Controller
{
    public function generateStoryJSON(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Validate request
        |--------------------------------------------------------------------------
        */

        Log::info('Validating story import request', [
            'request' => $request->all(),
        ]);

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

        try {

            /*
            |--------------------------------------------------------------------------
            | 2. Get uploaded file
            |--------------------------------------------------------------------------
            */

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
            | 5. Temporary S3 path
            |--------------------------------------------------------------------------
            */

            $storyPath = "temp/story-imports/{$importId}.txt";

            /*
            |--------------------------------------------------------------------------
            | 6. Upload story text to S3
            |--------------------------------------------------------------------------
            */

            $saved = Storage::disk('s3')->put(
                $storyPath,
                $storyText,
                [
                    'ContentType' => 'text/plain',
                ]
            );

            if (!$saved) {

                Log::error('Failed to save temporary story file to S3', [
                    'import_id' => $importId,
                    'story_path' => $storyPath,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save story file.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Verify S3 upload
            |--------------------------------------------------------------------------
            */

            if (!Storage::disk('s3')->exists($storyPath)) {

                Log::error('Temporary story file was not found after upload', [
                    'import_id' => $importId,
                    'story_path' => $storyPath,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Temporary story file could not be verified.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | 8. Log upload
            |--------------------------------------------------------------------------
            */

            Log::info('Temporary story uploaded to S3', [
                'import_id' => $importId,
                'story_path' => $storyPath,
                'size' => strlen($storyText),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 9. Dispatch GenerateStoryJob
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | Do NOT send $payload array.
            |
            | The GenerateStoryJob constructor expects:
            |
            | importId
            | title
            | description
            | storyPath
            |
            |--------------------------------------------------------------------------
            */

            GenerateStoryJob::dispatch(
                importId: $importId,
                title: $validated['title'],
                description: $validated['description'],
                storyPath: $storyPath
            );

            /*
            |--------------------------------------------------------------------------
            | 10. Log job
            |--------------------------------------------------------------------------
            */

            Log::info('GenerateStoryJob dispatched', [
                'import_id' => $importId,
                'story_path' => $storyPath,
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