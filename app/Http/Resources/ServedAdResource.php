<?php

namespace App\Http\Resources;

use App\Models\Artist;
use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The listener-facing view of a served ad: creative only, no targeting
 * rules or advertiser performance data — those stay private to the
 * advertiser's own dashboard (see AdvertisementResource).
 */
class ServedAdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'headline' => $this->headline,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'audio_url' => $this->audio_url,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'sponsorable' => match (true) {
                $this->sponsorable instanceof Playlist => new PlaylistResource($this->sponsorable),
                $this->sponsorable instanceof Artist => new ArtistResource($this->sponsorable),
                default => null,
            },
        ];
    }
}
