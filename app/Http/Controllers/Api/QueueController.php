<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    /**
     * Get pending jobs
     */
    public function jobs(): JsonResponse
    {
        $jobs = DB::table('jobs')
            ->orderBy('available_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $jobs->count(),
            'jobs' => $jobs,
        ]);
    }

    /**
     * Get failed jobs
     */
    public function failedJobs(): JsonResponse
    {
        $jobs = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $jobs->count(),
            'failed_jobs' => $jobs,
        ]);
    }

    /**
     * Get one failed job
     */
    public function failedJob(string $uuid): JsonResponse
    {
        $job = DB::table('failed_jobs')
            ->where('uuid', $uuid)
            ->first();

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Failed job not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'failed_job' => $job,
        ]);
    }
}