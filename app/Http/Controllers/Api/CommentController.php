<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Track;
use App\Notifications\NewCommentNotification;
use App\Services\Community\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(Track $track): AnonymousResourceCollection
    {
        return CommentResource::collection(
            $track->comments()->with('user')->latest()->paginate(20)
        );
    }

    public function store(StoreCommentRequest $request, Track $track): JsonResponse
    {
        $user = $request->user();

        $comment = $track->comments()->create([
            'user_id' => $user->id,
            'body' => $request->string('body'),
        ]);

        $this->activity->log($user, Activity::TYPE_COMMENTED_ON_TRACK, $track);

        if ($track->artist && $track->artist->user && $track->artist->user_id !== $user->id) {
            $track->artist->user->notify(new NewCommentNotification($user, $track, $comment));
        }

        return (new CommentResource($comment->load('user')))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}
