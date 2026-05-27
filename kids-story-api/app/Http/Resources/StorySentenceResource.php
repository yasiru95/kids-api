<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorySentenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'text' => $this->text,

            'words' => StoryWordResource::collection(
                $this->words
            ),
        ];
    }
}