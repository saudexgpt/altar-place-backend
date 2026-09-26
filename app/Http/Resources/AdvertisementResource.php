<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full campaign detail, for the owning advertiser's own dashboard/management
 * views. Includes targeting rules and performance counters — never expose
 * this to the listener-facing ad-serving endpoints (see ServedAdResource).
 */
class AdvertisementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'headline' => $this->headline,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'audio_url' => $this->audio_url,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'sponsorable_type' => $this->sponsorable_type ? class_basename($this->sponsorable_type) : null,
            'sponsorable_id' => $this->sponsorable_id,
            'targeting' => $this->targeting,
            'daily_impression_cap' => $this->daily_impression_cap,
            'impressions_count' => $this->impressions_count,
            'clicks_count' => $this->clicks_count,
            'click_through_rate' => $this->clickThroughRate(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
