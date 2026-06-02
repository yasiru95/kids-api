<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\ProcessStoryJob;
use Illuminate\Support\Facades\Log;

class GenerateStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $validated;

    public function __construct($validated)
    {
        $this->validated = $validated;
    }

    public function handle()
    {
        try {
            ProcessStoryJob::dispatch($this->validated);
        } catch (\Exception $e) {
            Log::error('Error dispatching ProcessStoryJob: ' . $e->getMessage());
        }
    }
}
