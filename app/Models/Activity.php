<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A log of the acting user's own social actions (followed, favorited,
 * commented, shared, created a playlist), rendered back to them as a
 * personal activity timeline. Scoped to the actor's own history for this
 * phase — not a cross-user "friend activity" fan-out feed.
 */
class Activity extends Model
{
    public const TYPE_FOLLOWED_ARTIST = 'followed_artist';

    public const TYPE_FOLLOWED_PLAYLIST = 'followed_playlist';

    public const TYPE_FAVORITED_TRACK = 'favorited_track';

    public const TYPE_COMMENTED_ON_TRACK = 'commented_on_track';

    public const TYPE_SHARED_TRACK = 'shared_track';

    public const TYPE_SHARED_PLAYLIST = 'shared_playlist';

    public const TYPE_CREATED_PLAYLIST = 'created_playlist';

    protected $fillable = ['user_id', 'type', 'subject_type', 'subject_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
