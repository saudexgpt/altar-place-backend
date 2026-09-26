<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Track;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private User $commenter, private Track $track, private Comment $comment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_comment',
            'commenter_id' => $this->commenter->id,
            'commenter_name' => $this->commenter->name,
            'track_id' => $this->track->id,
            'track_title' => $this->track->title,
            'comment_id' => $this->comment->id,
            'comment_excerpt' => Str::limit($this->comment->body, 80),
            'message' => "{$this->commenter->name} commented on \"{$this->track->title}\".",
        ];
    }
}
