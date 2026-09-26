<?php

namespace App\Policies;

use App\Models\Album;
use App\Models\User;

class AlbumPolicy
{
    public function update(User $user, Album $album): bool
    {
        return $user->hasRole('super-admin') || $album->artist->user_id === $user->id;
    }

    public function delete(User $user, Album $album): bool
    {
        return $this->update($user, $album);
    }
}
