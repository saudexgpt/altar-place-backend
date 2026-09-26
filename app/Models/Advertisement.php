<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class Advertisement extends Model
{
    protected $fillable = [
        'advertiser_id', 'type', 'status',
        'headline', 'body', 'image_url', 'audio_url', 'cta_label', 'cta_url',
        'sponsorable_type', 'sponsorable_id',
        'targeting', 'daily_impression_cap',
        'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'targeting' => 'array',
            'daily_impression_cap' => 'integer',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    public function sponsorable(): MorphTo
    {
        return $this->morphTo();
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(AdImpression::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AdClick::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function clickThroughRate(): float
    {
        return $this->impressions_count > 0
            ? round($this->clicks_count / $this->impressions_count * 100, 2)
            : 0.0;
    }

    public function hasReachedDailyCap(): bool
    {
        if ($this->daily_impression_cap === null) {
            return false;
        }

        $todayImpressions = $this->impressions()->whereDate('created_at', today())->count();

        return $todayImpressions >= $this->daily_impression_cap;
    }

    /**
     * Whether this campaign's targeting rules allow it to be shown to the
     * given context. A dimension with no rules configured matches everyone;
     * a dimension with rules only matches when the corresponding signal is
     * known and present in the allowed list.
     */
    public function matches(?User $user, ?string $deviceType, ?int $listenerGenreId): bool
    {
        $targeting = $this->targeting ?? [];

        if (! empty($targeting['device_types']) && ! in_array($deviceType, $targeting['device_types'], true)) {
            return false;
        }

        if (! empty($targeting['genre_ids']) && ! in_array($listenerGenreId, $targeting['genre_ids'], true)) {
            return false;
        }

        if (! empty($targeting['countries']) && ! in_array($user?->country, $targeting['countries'], true)) {
            return false;
        }

        if (! empty($targeting['states']) && ! in_array($user?->state, $targeting['states'], true)) {
            return false;
        }

        if (! empty($targeting['cities']) && ! in_array($user?->city, $targeting['cities'], true)) {
            return false;
        }

        if (($targeting['age_min'] ?? null) !== null || ($targeting['age_max'] ?? null) !== null) {
            $age = $user?->date_of_birth?->age;

            if ($age === null) {
                return false;
            }

            if (($targeting['age_min'] ?? null) !== null && $age < $targeting['age_min']) {
                return false;
            }

            if (($targeting['age_max'] ?? null) !== null && $age > $targeting['age_max']) {
                return false;
            }
        }

        return true;
    }
}
