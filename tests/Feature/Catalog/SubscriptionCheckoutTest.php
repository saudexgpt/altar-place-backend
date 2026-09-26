<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;

beforeEach(function () {
    $this->seed(SubscriptionPlanSeeder::class);
});

test('subscription plans are publicly listable', function () {
    $response = $this->getJson('/api/subscription-plans');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(4);
});

test('checkout requires authentication', function () {
    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->first();

    $this->postJson('/api/subscription/checkout', ['plan_id' => $plan->id, 'provider' => 'paystack'])
        ->assertUnauthorized();
});

test('checkout creates a pending subscription and payment, and returns a checkout url', function () {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->first();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/subscription/checkout', [
        'plan_id' => $plan->id,
        'provider' => 'paystack',
    ]);

    $response->assertCreated();
    expect($response->json('authorization_url'))->not->toBeNull();
    expect($response->json('reference'))->not->toBeNull();

    expect(Subscription::where('user_id', $user->id)->where('status', 'pending')->exists())->toBeTrue();
    expect(Payment::where('user_id', $user->id)->where('status', 'pending')->where('amount', $plan->price)->exists())->toBeTrue();
});

test('checkout rejects the free plan', function () {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::where('slug', 'free')->first();

    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/checkout', [
        'plan_id' => $plan->id,
        'provider' => 'paystack',
    ])->assertStatus(422);
});

test('checkout requires a valid provider', function () {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->first();

    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/checkout', [
        'plan_id' => $plan->id,
        'provider' => 'bitcoin',
    ])->assertUnprocessable()->assertJsonValidationErrors('provider');
});

test('a bank transfer checkout is a valid provider choice', function () {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->first();

    $this->actingAs($user, 'sanctum')->postJson('/api/subscription/checkout', [
        'plan_id' => $plan->id,
        'provider' => 'bank_transfer',
    ])->assertCreated();
});
