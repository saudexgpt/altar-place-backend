<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'is_verified' => $this->is_verified,
            'followers_count' => $this->when(isset($this->followers_count), $this->followers_count),
            'is_following' => $this->when(
                $request->user('sanctum') !== null,
                fn () => $this->followers()->where('user_id', $request->user('sanctum')?->id)->exists()
            ),
        ];
    }
}
