<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
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
            'cover_url' => $this->cover_url,
            'description' => $this->description,
            'release_year' => $this->release_year,
            'tracks_count' => $this->when(isset($this->tracks_count), $this->tracks_count),
            'artist' => new ArtistResource($this->whenLoaded('artist')),
            'genre' => new GenreResource($this->whenLoaded('genre')),
            'tracks' => TrackResource::collection($this->whenLoaded('tracks')),
        ];
    }
}
