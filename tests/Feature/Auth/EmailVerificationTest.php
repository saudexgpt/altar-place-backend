<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('email can be verified via a valid signed link', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->getJson($verificationUrl);

    $response->assertOk();
    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('email verification fails with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email@example.com')]
    );

    $response = $this->getJson($verificationUrl);

    $response->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an authenticated user can resend the verification email', function () {
    Illuminate\Support\Facades\Notification::fake();

    $user = User::factory()->unverified()->create();
    $token = $user->createToken('pest-test-device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/email/verification-notification');

    $response->assertOk();
    Illuminate\Support\Facades\Notification::assertSentTo($user, App\Notifications\VerifyEmailNotification::class);
});
