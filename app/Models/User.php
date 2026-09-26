<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\FrontendUrl;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'bio',
        'email',
        'password',
        'provider',
        'provider_id',
        'avatar_url',
        'status',
        'notification_preferences',
        'country',
        'state',
        'city',
        'date_of_birth',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'date_of_birth' => 'date',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function artist(): HasOne
    {
        return $this->hasOne(Artist::class);
    }

    public function advertiser(): HasOne
    {
        return $this->hasOne(Advertiser::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function searchHistories(): HasMany
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function reportsFiled(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function playHistories(): HasMany
    {
        return $this->hasMany(PlayHistory::class);
    }

    public function followers(): MorphMany
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function familyMemberships(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->currentlyActive()->latest('ends_at')->first();
    }

    /**
     * Resolves the plan currently entitling this user: their own active
     * subscription, or the plan of a family subscription they're an active
     * member of, falling back to the Free plan otherwise.
     */
    public function currentPlan(): SubscriptionPlan
    {
        if ($subscription = $this->activeSubscription()) {
            return $subscription->plan;
        }

        $familyMembership = $this->familyMemberships()
            ->where('status', 'active')
            ->whereHas('subscription', fn ($q) => $q->currentlyActive())
            ->with('subscription.plan')
            ->first();

        if ($familyMembership) {
            return $familyMembership->subscription->plan;
        }

        return SubscriptionPlan::where('slug', 'free')->firstOrFail();
    }

    public function isPremium(): bool
    {
        return ! $this->currentPlan()->isFree();
    }

    public function defaultNotificationPreferences(): array
    {
        return [
            'new_releases' => true,
            'followed_artist_uploads' => true,
            'comments_and_likes' => true,
            'email_digest' => false,
            'new_reports' => true,
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token, FrontendUrl::resolve()));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification(FrontendUrl::resolve()));
    }
}
