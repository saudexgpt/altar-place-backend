<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(SubscriptionPlanSeeder::class);
});

function checkout(User $user, string $planSlug, string $provider = 'paystack'): string
{
    $plan = SubscriptionPlan::where('slug', $planSlug)->first();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/subscription/checkout', [
        'plan_id' => $plan->id,
        'provider' => $provider,
    ]);

    return $response->json('reference');
}

test('verifying a fake checkout activates the subscription with the correct period', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');
    $user = User::factory()->create();
    $reference = checkout($user, 'premium-monthly');

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/subscription/verify', ['reference' => $reference]);

    $response->assertOk();
    expect($response->json('subscription.status'))->toBe('active');
    expect($response->json('subscription.ends_at'))->toContain('2026-02-01');
    expect($user->fresh()->isPremium())->toBeTrue();

    Carbon::setTestNow();
});

test('a yearly plan computes a one-year expiry', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');
    $user = User::factory()->create();
    $reference = checkout($user, 'premium-yearly');

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/subscription/verify', ['reference' => $reference]);

    expect($response->json('subscription.ends_at'))->toContain('2027-01-01');

    Carbon::setTestNow();
});

test('verifying twice is idempotent', function () {
    $user = User::factory()->create();
    $reference = checkout($user, 'premium-monthly');

    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/verify', ['reference' => $reference])->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/verify', ['reference' => $reference])->assertOk();

    expect(Subscription::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1);
});

test('subscribing again supersedes the previous active subscription', function () {
    $user = User::factory()->create();

    $firstReference = checkout($user, 'premium-monthly');
    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/verify', ['reference' => $firstReference]);

    $secondReference = checkout($user, 'premium-yearly');
    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/verify', ['reference' => $secondReference]);

    expect(Subscription::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1);
    expect(Subscription::where('user_id', $user->id)->where('status', 'expired')->count())->toBe(1);
    expect($user->fresh()->currentPlan()->slug)->toBe('premium-yearly');
});

test('a user without a subscription is on the free plan', function () {
    $user = User::factory()->create();

    expect($user->currentPlan()->slug)->toBe('free');
    expect($user->isPremium())->toBeFalse();
});

test('canceling keeps access until the paid period ends', function () {
    $user = User::factory()->create();
    $reference = checkout($user, 'premium-monthly');
    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/verify', ['reference' => $reference]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/subscription/cancel');

    $response->assertOk();
    expect($user->fresh()->isPremium())->toBeTrue();
    expect(Subscription::where('user_id', $user->id)->first()->canceled_at)->not->toBeNull();
});

test('the expire-subscriptions command downgrades lapsed subscriptions', function () {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->first();

    $subscription = $user->subscriptions()->create([
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subDay(),
    ]);

    $this->artisan('app:expire-subscriptions')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe('expired');
    expect($user->fresh()->currentPlan()->slug)->toBe('free');
});
