<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'cover_url' => $this->cover_url,
            'is_public' => $this->is_public,
            'is_curated' => $this->is_curated,
            'is_owner' => $this->when($request->user('sanctum') !== null, $this->user_id === $request->user('sanctum')?->id),
            'tracks_count' => $this->when(isset($this->tracks_count), $this->tracks_count),
            'shares_count' => $this->when(isset($this->shares_count), $this->shares_count),
            'owner' => new UserResource($this->whenLoaded('user')),
            'tracks' => TrackResource::collection($this->whenLoaded('tracks')),
        ];
    }
}
