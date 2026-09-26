<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Roles assignable from this UI. super-admin is deliberately excluded —
     * granting/revoking that role is dangerous enough to stay a
     * `php artisan tinker` operation rather than a button in the panel.
     *
     * @var list<string>
     */
    private const ASSIGNABLE_ROLES = ['listener', 'creator', 'advertiser', 'moderator'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->orderByDesc('created_at');

        if ($search = $request->string('q')->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($role = $request->string('role')->toString()) {
            $query->role($role);
        }

        return AdminUserResource::collection($query->paginate(20));
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $this->guardModerationTarget($request, $user);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $user->forceFill([
            'status' => 'suspended',
            'status_reason' => $request->input('reason'),
        ])->save();

        $user->tokens()->delete();

        return response()->json(['user' => new AdminUserResource($user)]);
    }

    public function ban(Request $request, User $user): JsonResponse
    {
        $this->guardModerationTarget($request, $user);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $user->forceFill([
            'status' => 'banned',
            'status_reason' => $request->input('reason'),
        ])->save();

        $user->tokens()->delete();

        return response()->json(['user' => new AdminUserResource($user)]);
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        $this->guardModerationTarget($request, $user);

        $user->forceFill([
            'status' => 'active',
            'status_reason' => null,
        ])->save();

        return response()->json(['user' => new AdminUserResource($user)]);
    }

    public function verify(User $user): JsonResponse
    {
        $user->forceFill([
            'is_verified' => true,
            'verified_at' => now(),
        ])->save();

        return response()->json(['user' => new AdminUserResource($user)]);
    }

    /**
     * A fuller detail view than the list row: subscription status, and — if
     * applicable — their Artist or Advertiser profile with basic counts.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['artist', 'advertiser']);
        $subscription = $user->activeSubscription();

        return response()->json([
            'user' => new AdminUserResource($user),
            'subscription' => $subscription ? [
                'plan' => $subscription->plan->name,
                'status' => $subscription->status,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ] : null,
            'artist' => $user->artist ? [
                'id' => $user->artist->id,
                'name' => $user->artist->name,
                'tracks_count' => $user->artist->tracks()->count(),
                'followers_count' => $user->artist->followers()->count(),
            ] : null,
            'advertiser' => $user->advertiser ? [
                'id' => $user->advertiser->id,
                'company_name' => $user->advertiser->company_name,
                'campaigns_count' => $user->advertiser->advertisements()->count(),
            ] : null,
        ]);
    }

    public function updateRoles(Request $request, User $user): JsonResponse
    {
        abort_if($user->hasRole('super-admin'), 403, 'A super-admin account\'s roles cannot be changed here.');
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own roles.');

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(self::ASSIGNABLE_ROLES)],
            'action' => ['required', 'string', 'in:assign,remove'],
        ]);

        if ($validated['action'] === 'assign') {
            $user->assignRole($validated['role']);
        } else {
            $user->removeRole($validated['role']);
        }

        return response()->json(['user' => new AdminUserResource($user->fresh())]);
    }

    private function guardModerationTarget(Request $request, User $user): void
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot moderate your own account.');
        abort_if($user->hasRole('super-admin'), 403, 'A super-admin account cannot be moderated.');
    }
}
