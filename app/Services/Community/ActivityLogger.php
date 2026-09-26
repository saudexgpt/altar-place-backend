<?php

namespace App\Services\Community;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function log(User $user, string $type, Model $subject): void
    {
        Activity::create([
            'user_id' => $user->id,
            'type' => $type,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
        ]);
    }
}
