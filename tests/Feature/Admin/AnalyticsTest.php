<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SubscriptionPlanSeeder::class);
});

test('a listener cannot access the analytics overview', function () {
    $listener = User::factory()->create();
    $listener->assignRole('listener');

    $this->actingAs($listener, 'sanctum')->getJson('/api/admin/analytics/overview')->assertForbidden();
});

test('analytics overview reports dau, mau, revenue, conversion and churn', function () {
    Carbon::setTestNow('2026-08-24 12:00:00');

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $activeToday = User::factory()->create(['last_active_at' => now()]);
    $activeThisMonth = User::factory()->create(['last_active_at' => now()->subDays(10)]);
    User::factory()->create(['last_active_at' => now()->subDays(90)]);
    User::factory()->create(['last_active_at' => null]);

    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->firstOrFail();

    $payingUser = User::factory()->create(['last_active_at' => now()]);
    $activeSubscription = Subscription::create([
        'user_id' => $payingUser->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);

    Payment::create([
        'user_id' => $payingUser->id,
        'subscription_id' => $activeSubscription->id,
        'provider' => 'paystack',
        'reference' => 'ref-1',
        'amount' => 150000,
        'currency' => 'NGN',
        'status' => 'successful',
        'paid_at' => now()->subDays(5),
    ]);

    $churnedUser = User::factory()->create();
    Subscription::create([
        'user_id' => $churnedUser->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'canceled',
        'starts_at' => now()->subDays(20),
        'ends_at' => now()->subDays(1),
        'canceled_at' => now()->subDays(1),
    ]);

    $response = $this->actingAs($moderator, 'sanctum')->getJson('/api/admin/analytics/overview');

    $response->assertOk();
    // dau: $moderator + $activeToday + $payingUser were stamped `now()` (either
    // explicitly or via the TrackUserActivity middleware on this very request).
    expect($response->json('dau'))->toBeGreaterThanOrEqual(3);
    expect($response->json('mau'))->toBeGreaterThanOrEqual(4);
    expect($response->json('revenue.total'))->toBe(150000);
    expect($response->json('active_subscriptions'))->toBe(1);
    expect($response->json('conversion_rate'))->toBeGreaterThan(0);
    expect($response->json('churn_rate'))->toBeGreaterThan(0);

    Carbon::setTestNow();
});
