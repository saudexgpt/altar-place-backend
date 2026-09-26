<?php

namespace App\Http\Resources;

use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'subject' => match (true) {
                $this->subject instanceof Track => new TrackResource($this->subject),
                $this->subject instanceof Artist => new ArtistResource($this->subject),
                $this->subject instanceof Playlist => new PlaylistResource($this->subject),
                default => null,
            },
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
