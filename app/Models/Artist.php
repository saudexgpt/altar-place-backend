<?php

namespace App\Models;

use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'slug', 'avatar_url', 'bio', 'is_verified'];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function followers(): MorphMany
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    /**
     * The shared artist credited for tracks admins/moderators upload
     * directly from the Admin Panel (not tied to any one staff member's
     * personal account, since several admins may publish platform
     * resources over time).
     */
    public static function official(): self
    {
        return self::firstOrCreate(
            ['slug' => 'altarplace-official'],
            ['name' => 'Altar Place', 'is_verified' => true]
        );
    }
}
