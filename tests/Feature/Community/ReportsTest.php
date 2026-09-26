<?php

use App\Models\Report;
use App\Models\Track;
use App\Models\User;
use App\Notifications\NewReportFiledNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

test('a listener can report a track', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/reports', [
        'reportable_type' => 'track',
        'reportable_id' => $track->id,
        'reason' => 'copyright',
        'details' => 'This is a re-upload of someone else\'s sermon.',
    ]);

    $response->assertCreated();
    expect(Report::where('reportable_type', Track::class)->where('reportable_id', $track->id)->exists())->toBeTrue();
    expect($response->json('data.reason'))->toBe('copyright');
});

test('a guest cannot file a report', function () {
    $track = Track::factory()->create();

    $this->postJson('/api/reports', [
        'reportable_type' => 'track',
        'reportable_id' => $track->id,
        'reason' => 'spam',
    ])->assertUnauthorized();
});

test('reporting requires a valid reportable type', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/reports', [
        'reportable_type' => 'user',
        'reportable_id' => $user->id,
        'reason' => 'spam',
    ]);

    $response->assertStatus(422);
});

test('reporting a nonexistent track fails', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/reports', [
        'reportable_type' => 'track',
        'reportable_id' => 999999,
        'reason' => 'spam',
    ]);

    $response->assertNotFound();
});

test('filing a report notifies staff who have not opted out', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $optedOut = User::factory()->create(['notification_preferences' => ['new_reports' => false]]);
    $optedOut->assignRole('moderator');

    $listener = User::factory()->create();
    $listener->assignRole('listener');

    $reporter = User::factory()->create();
    $track = Track::factory()->create();

    $this->actingAs($reporter, 'sanctum')->postJson('/api/reports', [
        'reportable_type' => 'track',
        'reportable_id' => $track->id,
        'reason' => 'spam',
    ])->assertCreated();

    Notification::assertSentTo($moderator, NewReportFiledNotification::class);
    Notification::assertNotSentTo($optedOut, NewReportFiledNotification::class);
    Notification::assertNotSentTo($listener, NewReportFiledNotification::class);
});
