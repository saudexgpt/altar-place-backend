<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InviteFamilyMemberRequest;
use App\Models\FamilyMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subscription = $this->ownedFamilySubscription($request);

        return response()->json([
            'members' => $subscription->familyMembers()->with('user:id,name,email,avatar_url')->get()->map(fn (FamilyMember $member) => [
                'id' => $member->id,
                'invited_email' => $member->invited_email,
                'status' => $member->status,
                'user' => $member->user,
            ]),
            'max_family_members' => $subscription->plan->max_family_members,
        ]);
    }

    public function store(InviteFamilyMemberRequest $request): JsonResponse
    {
        $subscription = $this->ownedFamilySubscription($request);

        $limit = $subscription->plan->max_family_members ?? 0;
        $currentCount = $subscription->familyMembers()->where('status', 'active')->count();

        abort_if($currentCount >= $limit, 422, "This plan allows up to {$limit} family members.");

        $invitedUser = User::where('email', $request->string('email'))->first();

        abort_unless($invitedUser, 422, 'No account found with that email. Ask them to sign up first.');
        abort_if($invitedUser->id === $request->user()->id, 422, 'You cannot invite yourself.');

        $member = $subscription->familyMembers()->updateOrCreate(
            ['invited_email' => $invitedUser->email],
            ['user_id' => $invitedUser->id, 'status' => 'active']
        );

        return response()->json(['member' => $member], 201);
    }

    public function destroy(Request $request, FamilyMember $familyMember): JsonResponse
    {
        $subscription = $this->ownedFamilySubscription($request);

        abort_unless($familyMember->subscription_id === $subscription->id, 403);

        $familyMember->update(['status' => 'removed']);

        return response()->json(['message' => 'Family member removed.']);
    }

    private function ownedFamilySubscription(Request $request): Subscription
    {
        $subscription = $request->user()->activeSubscription();

        abort_unless($subscription && $subscription->plan->slug === 'family', 403, 'You need an active Family Plan subscription.');

        return $subscription;
    }
}
