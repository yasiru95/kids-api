<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryWordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'text' => $this->text,

            'start' => $this->start_time,

            'end' => $this->end_time,
        ];
    }
}