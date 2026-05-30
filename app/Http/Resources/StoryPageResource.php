<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'img' => $this->image,

            'audio' => $this->audio,

            'sentences' => StorySentenceResource::collection(
                $this->sentences
            ),
        ];
    }
}