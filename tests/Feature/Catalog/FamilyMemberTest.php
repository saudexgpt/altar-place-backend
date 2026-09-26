<?php

use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;

beforeEach(function () {
    $this->seed(SubscriptionPlanSeeder::class);
});

function withFamilyPlan(User $user): \App\Models\Subscription
{
    $plan = SubscriptionPlan::where('slug', 'family')->first();

    return $user->subscriptions()->create([
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);
}

test('a family plan owner can invite an existing user by email', function () {
    $owner = User::factory()->create();
    withFamilyPlan($owner);
    $invitee = User::factory()->create();

    $response = $this->actingAs($owner, 'sanctum')->postJson('/api/subscription/family-members', [
        'email' => $invitee->email,
    ]);

    $response->assertCreated();
    expect($invitee->fresh()->currentPlan()->slug)->toBe('family');
});

test('inviting an unregistered email is rejected', function () {
    $owner = User::factory()->create();
    withFamilyPlan($owner);

    $this->actingAs($owner, 'sanctum')->postJson('/api/subscription/family-members', [
        'email' => 'nobody@example.com',
    ])->assertStatus(422);
});

test('a non-family-plan user cannot invite members', function () {
    $user = User::factory()->create();
    $invitee = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/family-members', [
        'email' => $invitee->email,
    ])->assertForbidden();
});

test('the family member limit is enforced', function () {
    $owner = User::factory()->create();
    $subscription = withFamilyPlan($owner);
    $plan = $subscription->plan;

    foreach (range(1, $plan->max_family_members) as $_) {
        $subscription->familyMembers()->create([
            'invited_email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);
    }

    $invitee = User::factory()->create();

    $this->actingAs($owner, 'sanctum')->postJson('/api/subscription/family-members', [
        'email' => $invitee->email,
    ])->assertStatus(422);
});

test('an owner can remove a family member', function () {
    $owner = User::factory()->create();
    $subscription = withFamilyPlan($owner);
    $invitee = User::factory()->create();

    $add = $this->actingAs($owner, 'sanctum')->postJson('/api/subscription/family-members', ['email' => $invitee->email]);
    $memberId = $add->json('member.id');

    $this->actingAs($owner, 'sanctum')->deleteJson("/api/subscription/family-members/{$memberId}")->assertOk();

    expect($invitee->fresh()->currentPlan()->slug)->toBe('free');
});
