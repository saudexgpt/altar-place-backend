<?php

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Notification;

test('a password reset requested from the web app links to the web app, not the mobile app', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->withHeader('Origin', 'http://127.0.0.1:8000')
        ->postJson('/api/auth/forgot-password', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, rtrim(config('app.url'), '/').'/reset-password')
            && ! str_contains($url, (string) config('app.frontend_url'));
    });
});

test('a password reset requested with no web origin (mobile) links to the mobile app', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, rtrim((string) config('app.frontend_url'), '/').'/reset-password');
    });
});

test('a verification email requested from the web app links to the web app', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $token = $user->createToken('pest-test-device')->plainTextToken;

    $this->withHeader('Origin', 'http://127.0.0.1:8000')
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/email/verification-notification')
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmailNotification::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, rtrim(config('app.url'), '/').'/verify-email?');
    });
});

test('a subscription checkout started from the web app calls back to the web app', function () {
    $this->seed(SubscriptionPlanSeeder::class);
    $user = User::factory()->create();
    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->first();

    $response = $this->withHeader('Origin', 'http://127.0.0.1:8000')
        ->actingAs($user, 'sanctum')
        ->postJson('/api/subscription/checkout', ['plan_id' => $plan->id, 'provider' => 'paystack']);

    $response->assertCreated();
    expect($response->json('authorization_url'))->toContain(rtrim(config('app.url'), '/').'/subscription/callback');
});

test('a subscription checkout started from mobile calls back to the mobile app', function () {
    $this->seed(SubscriptionPlanSeeder::class);
    $user = User::factory()->create();
    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->first();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/subscription/checkout', ['plan_id' => $plan->id, 'provider' => 'paystack']);

    $response->assertCreated();
    expect($response->json('authorization_url'))->toContain(rtrim((string) config('app.frontend_url'), '/').'/subscription/callback');
});
