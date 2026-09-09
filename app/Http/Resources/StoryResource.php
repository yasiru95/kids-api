<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            
            'id' => $this->id,

            'title' => $this->title,

            'slug' => $this->slug,

            'description' => $this->description,

            'image' => $this->image,

            'category' => $this->category,

            'age_group' => $this->age_group,

            'free' => (bool) $this->is_free,

            'pages' => StoryPageResource::collection(
                $this->pages
            ),
        ];
    }
}