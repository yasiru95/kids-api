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

            $file = $request->file('storyFile');

            if (!$file) {

                return response()->json([
                    'success' => false,
                    'message' => 'storyFile is missing in request',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Read TXT file
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

            if (trim($storyText) === '') {

                return response()->json([
                    'success' => false,
                    'message' => 'Story file is empty.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Create unique import ID
            |--------------------------------------------------------------------------
            */

            $importId = (string) Str::uuid();

            /*
            |--------------------------------------------------------------------------
            | Store story text
            |--------------------------------------------------------------------------
            |
            | Do NOT put the entire story inside the queue payload.
            |
            */

            $filePath = "story-imports/{$importId}.txt";

            Storage::disk('local')->put(
                $filePath,
                $storyText
            );

            /*
            |--------------------------------------------------------------------------
            | Queue payload
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
            | Dispatch background job
            |--------------------------------------------------------------------------
            */

            GenerateStoryJob::dispatch($payload);

            /*
            |--------------------------------------------------------------------------
            | Return immediately
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


//    try {
//         $validated = $request->validate([
//             'title' => 'required|string|max:255',
//             'description' => 'required|string',
//             'storyFile' => 'required|file|mimes:txt|max:5120', // max 5MB text file
//         ]);
//     } catch (\Illuminate\Validation\ValidationException $e) {
//         Log::error('Validation failed: ' . json_encode($e->errors()));
//         return response()->json([
//             'success' => false,
//             'message' => 'Validation failed',
//             'errors' => $e->errors()
//         ], 422);
//     }

//     $file = $request->file('storyFile');

//     if (!$file) {
//     return response()->json([
//         'success' => false,
//         'message' => 'storyFile is missing in request'
//     ], 422);
//     }

//     $storyText= file_get_contents($file->getRealPath());

//     // ✅ CLEAN PAYLOAD FOR JOB
//     $payload = [
//         'title' => $validated['title'],
//         'description' => $validated['description'],
//         'story' => $storyText,
//     ];


    

//     GenerateStoryJob::dispatch($payload);

//     return response()->json([
//         'success' => true,
//         'message' => 'Story is being processed in background queue.......'
//     ]);
}





    
       

    












    





    
}