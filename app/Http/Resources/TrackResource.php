<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackResource extends JsonResource
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
            'slug' => $this->slug,
            'type' => $this->type,
            'language' => $this->language,
            'duration_seconds' => $this->duration_seconds,
            'cover_url' => $this->resolvedCoverUrl(),
            'description' => $this->description,
            'is_explicit' => $this->is_explicit,
            'plays_count' => $this->plays_count,
            'release_date' => $this->release_date?->toDateString(),
            'transcoding_status' => $this->transcoding_status,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'stream_url' => route('tracks.stream', $this->id),
            'artist' => new ArtistResource($this->whenLoaded('artist')),
            'album' => new AlbumResource($this->whenLoaded('album')),
            'genre' => new GenreResource($this->whenLoaded('genre')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'is_favorited' => $this->when(
                $request->user('sanctum') !== null,
                fn () => $this->favorites()->where('user_id', $request->user('sanctum')?->id)->exists()
            ),
            'comments_count' => $this->when(isset($this->comments_count), $this->comments_count),
            'shares_count' => $this->when(isset($this->shares_count), $this->shares_count),
        ];
    }
}
