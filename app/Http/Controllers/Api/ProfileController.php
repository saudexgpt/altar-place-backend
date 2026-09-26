<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\UserResource;
use App\Models\Artist;
use App\Models\Playlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user()->fresh());
    }

    public function uploadAvatar(Request $request): UserResource
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:4096'],
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar_url' => Storage::disk('public')->url($path)]);

        return new UserResource($user->fresh());
    }

    public function updateNotificationPreferences(Request $request): UserResource
    {
        $validated = $request->validate([
            'new_releases' => ['sometimes', 'boolean'],
            'followed_artist_uploads' => ['sometimes', 'boolean'],
            'comments_and_likes' => ['sometimes', 'boolean'],
            'email_digest' => ['sometimes', 'boolean'],
            'new_reports' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $preferences = array_merge($user->notification_preferences ?? $user->defaultNotificationPreferences(), $validated);
        $user->update(['notification_preferences' => $preferences]);

        return new UserResource($user->fresh());
    }

    public function following(Request $request): JsonResponse
    {
        $user = $request->user();

        $artistIds = $user->follows()->where('followable_type', Artist::class)->pluck('followable_id');
        $playlistIds = $user->follows()->where('followable_type', Playlist::class)->pluck('followable_id');

        return response()->json([
            'artists' => ArtistResource::collection(Artist::whereIn('id', $artistIds)->withCount('followers')->get()),
            'playlists' => PlaylistResource::collection(Playlist::whereIn('id', $playlistIds)->withCount('tracks')->get()),
        ]);
    }
}
