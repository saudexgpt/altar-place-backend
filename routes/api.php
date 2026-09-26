<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\AdminModerationController;
use App\Http\Controllers\Api\Admin\AdminPlatformSettingsController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AdsController;
use App\Http\Controllers\Api\AdvertiserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CreatorAlbumController;
use App\Http\Controllers\Api\CreatorController;
use App\Http\Controllers\Api\CreatorTrackController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PlatformStatusController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/social/{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('/social/{provider}/callback', [SocialAuthController::class, 'callback'])->name('auth.social.callback');
    Route::post('/social/exchange', [SocialAuthController::class, 'exchange']);

    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-other-devices', [AuthController::class, 'logoutOtherDevices']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
            ->middleware('throttle:6,1');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::delete('/devices/{tokenId}', [DeviceController::class, 'destroy']);
});

// Catalog & discovery: public/guest-accessible browsing. Personalization
// (is_favorited, is_following, recommendations) is resolved from an
// optional Bearer token inside the resources/controllers themselves.
Route::get('/genres', [CatalogController::class, 'genres']);
Route::get('/tags', [CatalogController::class, 'tags']);
Route::get('/artists/{artist}', [CatalogController::class, 'showArtist']);
Route::get('/artists/{artist}/tracks', [CatalogController::class, 'artistTracks']);
Route::get('/artists/{artist}/similar', [CatalogController::class, 'similarArtists']);
Route::get('/albums/{album}', [CatalogController::class, 'showAlbum']);
Route::get('/tracks/{track}', [CatalogController::class, 'showTrack']);
Route::get('/tracks/{track}/stream', [StreamController::class, 'stream'])->name('tracks.stream');
Route::get('/search', SearchController::class);

Route::prefix('discovery')->group(function (): void {
    Route::get('/trending', [DiscoveryController::class, 'trending']);
    Route::get('/new-releases', [DiscoveryController::class, 'newReleases']);
    Route::get('/recommended', [DiscoveryController::class, 'recommended']);
    Route::get('/recommended-podcasts', [DiscoveryController::class, 'recommendedPodcasts']);
    Route::get('/featured-artists', [DiscoveryController::class, 'featuredArtists']);
    Route::get('/featured-podcasts', [DiscoveryController::class, 'featuredPodcasts']);
    Route::get('/featured-sermons', [DiscoveryController::class, 'featuredSermons']);
    Route::get('/popular-playlists', [DiscoveryController::class, 'popularPlaylists']);
});

// Daily Mix / Discover Weekly require a real listener identity (they're
// per-user stable playlists, not guest-friendly like the rest of discovery).
Route::middleware('auth:sanctum')->prefix('discovery')->group(function (): void {
    Route::get('/daily-mix', [DiscoveryController::class, 'dailyMix']);
    Route::get('/discover-weekly', [DiscoveryController::class, 'discoverWeekly']);
});

Route::get('/playlists', [PlaylistController::class, 'index']);
Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);

// Comments: guest-readable like catalog, posting/deleting requires auth.
Route::get('/tracks/{track}/comments', [CommentController::class, 'index']);

Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);

Route::get('/platform/status', [PlatformStatusController::class, 'show']);

// Ad serving & tracking: guest-accessible like catalog/discovery — an
// optional Bearer token personalizes targeting but is never required.
Route::get('/ads/serve', [AdsController::class, 'serve']);
Route::post('/ads/{advertisement}/impression', [AdsController::class, 'impression']);
Route::post('/ads/{advertisement}/click', [AdsController::class, 'click']);

Route::post('/webhooks/{provider}', [PaymentWebhookController::class, 'handle'])
    ->where('provider', 'paystack|flutterwave')
    ->name('webhooks.payments');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::put('/profile/notification-preferences', [ProfileController::class, 'updateNotificationPreferences']);
    Route::get('/profile/following', [ProfileController::class, 'following']);

    Route::post('/artists/{artist}/follow', [CatalogController::class, 'followArtist']);
    Route::delete('/artists/{artist}/follow', [CatalogController::class, 'unfollowArtist']);

    Route::post('/tracks/{track}/favorite', [LibraryController::class, 'favoriteTrack']);
    Route::delete('/tracks/{track}/favorite', [LibraryController::class, 'unfavoriteTrack']);

    Route::post('/tracks/{track}/comments', [CommentController::class, 'store']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    Route::post('/tracks/{track}/share', [LibraryController::class, 'shareTrack']);
    Route::post('/tracks/{track}/complete', [LibraryController::class, 'markTrackComplete']);

    Route::get('/activity', [ActivityController::class, 'index']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::get('/library/favorites', [LibraryController::class, 'favorites']);
    Route::get('/library/recently-played', [LibraryController::class, 'recentlyPlayed']);
    Route::get('/library/playlists', [LibraryController::class, 'playlists']);

    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);
    Route::post('/playlists/{playlist}/tracks/{track}', [PlaylistController::class, 'addTrack']);
    Route::delete('/playlists/{playlist}/tracks/{track}', [PlaylistController::class, 'removeTrack']);
    Route::post('/playlists/{playlist}/follow', [PlaylistController::class, 'follow']);
    Route::delete('/playlists/{playlist}/follow', [PlaylistController::class, 'unfollow']);
    Route::post('/playlists/{playlist}/share', [PlaylistController::class, 'share']);

    Route::post('/tracks/{track}/download-event', [LibraryController::class, 'logDownload']);
    Route::get('/library/download-quota', [LibraryController::class, 'downloadQuota']);

    Route::get('/subscription', [SubscriptionController::class, 'status']);
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/subscription/verify', [SubscriptionController::class, 'verify']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);

    Route::get('/subscription/family-members', [FamilyMemberController::class, 'index']);
    Route::post('/subscription/family-members', [FamilyMemberController::class, 'store']);
    Route::delete('/subscription/family-members/{familyMember}', [FamilyMemberController::class, 'destroy']);

    Route::post('/creator/apply', [CreatorController::class, 'apply']);

    Route::middleware('role:creator|super-admin')->prefix('creator')->group(function (): void {
        Route::get('/dashboard', [CreatorController::class, 'dashboard']);
        Route::get('/analytics', [CreatorController::class, 'analytics']);

        Route::get('/tracks', [CreatorTrackController::class, 'index']);
        Route::post('/tracks', [CreatorTrackController::class, 'store']);
        Route::put('/tracks/{track}', [CreatorTrackController::class, 'update']);
        Route::delete('/tracks/{track}', [CreatorTrackController::class, 'destroy']);

        Route::get('/albums', [CreatorAlbumController::class, 'index']);
        Route::post('/albums', [CreatorAlbumController::class, 'store']);
        Route::put('/albums/{album}', [CreatorAlbumController::class, 'update']);
        Route::delete('/albums/{album}', [CreatorAlbumController::class, 'destroy']);
    });

    Route::post('/advertiser/apply', [AdvertiserController::class, 'apply']);

    Route::middleware('role:advertiser|super-admin')->prefix('advertiser')->group(function (): void {
        Route::get('/dashboard', [AdvertiserController::class, 'dashboard']);

        Route::get('/campaigns', [AdvertiserController::class, 'campaigns']);
        Route::post('/campaigns', [AdvertiserController::class, 'storeCampaign']);
        Route::put('/campaigns/{advertisement}', [AdvertiserController::class, 'updateCampaign']);
        Route::delete('/campaigns/{advertisement}', [AdvertiserController::class, 'destroyCampaign']);
    });

    Route::post('/reports', [ReportController::class, 'store']);

    Route::middleware('role:moderator|super-admin')->prefix('admin')->group(function (): void {
        Route::get('/analytics/overview', [AdminAnalyticsController::class, 'overview']);
        Route::get('/analytics/chart', [AdminAnalyticsController::class, 'chart']);
        Route::get('/analytics/top-content', [AdminAnalyticsController::class, 'topContent']);
        Route::get('/analytics/recent-activity', [AdminAnalyticsController::class, 'recentActivity']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban']);
        Route::post('/users/{user}/reactivate', [AdminUserController::class, 'reactivate']);
        Route::post('/users/{user}/verify', [AdminUserController::class, 'verify']);
        Route::put('/users/{user}/roles', [AdminUserController::class, 'updateRoles']);

        Route::get('/tracks', [AdminModerationController::class, 'tracks']);
        // Reuses CreatorTrackController::update — TrackPolicy already grants
        // access to anyone with the moderate-content permission, not just
        // the owning creator.
        Route::put('/tracks/{track}', [CreatorTrackController::class, 'update']);
        Route::post('/tracks/{track}/approve', [AdminModerationController::class, 'approveTrack']);
        Route::post('/tracks/{track}/reject', [AdminModerationController::class, 'rejectTrack']);

        Route::get('/reports', [AdminModerationController::class, 'reports']);
        Route::post('/reports/{report}/resolve', [AdminModerationController::class, 'resolveReport']);

        Route::middleware('role:super-admin')->group(function (): void {
            Route::put('/platform-settings/maintenance', [AdminPlatformSettingsController::class, 'updateMaintenance']);
        });
    });
});
