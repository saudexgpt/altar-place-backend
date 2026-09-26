<?php

namespace App\Policies;

use App\Models\Playlist;
use App\Models\User;

class PlaylistPolicy
{
    public function update(User $user, Playlist $playlist): bool
    {
        return $user->can('moderate-content') || ($playlist->user_id === $user->id && ! $playlist->is_curated);
    }

    public function delete(User $user, Playlist $playlist): bool
    {
        return $user->can('moderate-content') || ($playlist->user_id === $user->id && ! $playlist->is_curated);
    }

    public function manageTracks(User $user, Playlist $playlist): bool
    {
        return $user->can('moderate-content') || ($playlist->user_id === $user->id && ! $playlist->is_curated);
    }
}
