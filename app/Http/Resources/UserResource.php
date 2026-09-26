<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $this->bio,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'status' => $this->status,
            'email_verified' => $this->email_verified_at !== null,
            'roles' => $this->getRoleNames(),
            'notification_preferences' => $this->notification_preferences ?? $this->defaultNotificationPreferences(),
            'created_at' => $this->created_at,
        ];
    }
}
