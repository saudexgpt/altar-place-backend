<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'user' => new UserResource($this->whenLoaded('user')),
            'is_owner' => $this->when($request->user('sanctum') !== null, $this->user_id === $request->user('sanctum')?->id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
