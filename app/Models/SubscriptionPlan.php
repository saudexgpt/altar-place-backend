<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'price', 'currency', 'billing_interval',
        'max_family_members', 'features', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'price' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    public function isAdFree(): bool
    {
        return (bool) ($this->features['ad_free'] ?? false);
    }

    public function maxAudioQuality(): string
    {
        return $this->features['max_audio_quality'] ?? 'standard';
    }

    public function downloadLimit(): ?int
    {
        return $this->features['download_limit'] ?? null;
    }

    public function allowsOfflinePlayback(): bool
    {
        return (bool) ($this->features['offline_playback'] ?? false);
    }
}
