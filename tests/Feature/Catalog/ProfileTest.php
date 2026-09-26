<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a user can update their username and bio', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
        'username' => 'gracefulsinger',
        'bio' => 'Lover of worship music.',
    ]);

    $response->assertOk();
    expect($response->json('data.username'))->toBe('gracefulsinger');
    expect($response->json('data.bio'))->toBe('Lover of worship music.');
});

test('usernames must be unique', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', ['username' => 'taken']);

    $response->assertUnprocessable()->assertJsonValidationErrors('username');
});

test('a user can upload an avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertOk();
    expect($response->json('data.avatar_url'))->not->toBeNull();
});

test('a user can update notification preferences independently', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/notification-preferences', [
        'email_digest' => true,
    ]);

    $response->assertOk();
    expect($response->json('data.notification_preferences.email_digest'))->toBeTrue();
    expect($response->json('data.notification_preferences.new_releases'))->toBeTrue();
});

test('a staff member can opt out of new report notifications', function () {
    $moderator = User::factory()->create();

    $response = $this->actingAs($moderator, 'sanctum')->putJson('/api/profile/notification-preferences', [
        'new_reports' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.notification_preferences.new_reports'))->toBeFalse();
});
