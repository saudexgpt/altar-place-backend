<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTrackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'cover_url' => $this->resolvedCoverUrl(),
            'stream_url' => route('tracks.stream', $this->id),
            'plays_count' => $this->plays_count,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'moderated_at' => $this->moderated_at?->toIso8601String(),
            'artist' => $this->when($this->relationLoaded('artist') && $this->artist, fn () => [
                'id' => $this->artist->id,
                'name' => $this->artist->name,
            ]),
            'reports_count' => $this->when(isset($this->reports_count), $this->reports_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
