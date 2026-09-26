<?php

use App\Models\Artist;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SubscriptionPlanSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function moderator(): User
{
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    return $moderator;
}

test('a listener cannot access admin user routes', function () {
    $listener = User::factory()->create();
    $listener->assignRole('listener');
    $target = User::factory()->create();

    $this->actingAs($listener, 'sanctum')->getJson('/api/admin/users')->assertForbidden();
    $this->actingAs($listener, 'sanctum')->postJson("/api/admin/users/{$target->id}/suspend")->assertForbidden();
});

test('a moderator can list users', function () {
    User::factory()->count(3)->create();

    $response = $this->actingAs(moderator(), 'sanctum')->getJson('/api/admin/users');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(4); // 3 + the moderator
});

test('a moderator can suspend a user, which blocks their login and revokes tokens', function () {
    $target = User::factory()->create(['password' => bcrypt('password')]);
    $target->assignRole('listener');
    $token = $target->createToken('device')->plainTextToken;

    $response = $this->actingAs(moderator(), 'sanctum')->postJson("/api/admin/users/{$target->id}/suspend", [
        'reason' => 'Repeated spam comments.',
    ]);

    $response->assertOk();
    expect($target->fresh()->status)->toBe('suspended');
    expect($target->fresh()->status_reason)->toBe('Repeated spam comments.');
    expect($target->fresh()->tokens()->count())->toBe(0);

    $login = $this->postJson('/api/auth/login', [
        'email' => $target->email,
        'password' => 'password',
        'device_name' => 'test',
    ]);
    $login->assertStatus(422);
});

test('a moderator can ban a user', function () {
    $target = User::factory()->create();
    $target->assignRole('listener');

    $response = $this->actingAs(moderator(), 'sanctum')->postJson("/api/admin/users/{$target->id}/ban", [
        'reason' => 'Fraudulent activity.',
    ]);

    $response->assertOk();
    expect($target->fresh()->status)->toBe('banned');
});

test('reactivating a suspended user restores active status', function () {
    $target = User::factory()->create(['status' => 'suspended', 'status_reason' => 'x']);

    $response = $this->actingAs(moderator(), 'sanctum')->postJson("/api/admin/users/{$target->id}/reactivate");

    $response->assertOk();
    expect($target->fresh()->status)->toBe('active');
    expect($target->fresh()->status_reason)->toBeNull();
});

test('a moderator can verify a user', function () {
    $target = User::factory()->create();

    $response = $this->actingAs(moderator(), 'sanctum')->postJson("/api/admin/users/{$target->id}/verify");

    $response->assertOk();
    expect($target->fresh()->is_verified)->toBeTrue();
    expect($target->fresh()->verified_at)->not->toBeNull();
});

test('a moderator cannot suspend their own account', function () {
    $mod = moderator();

    $this->actingAs($mod, 'sanctum')->postJson("/api/admin/users/{$mod->id}/suspend")->assertStatus(422);
});

test('a super-admin account cannot be suspended or banned', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs(moderator(), 'sanctum')->postJson("/api/admin/users/{$superAdmin->id}/suspend")->assertForbidden();
    $this->actingAs(moderator(), 'sanctum')->postJson("/api/admin/users/{$superAdmin->id}/ban")->assertForbidden();
});

test('a moderator can view a user detail with subscription and creator info', function () {
    $this->seed(SubscriptionPlanSeeder::class);

    $target = User::factory()->create();
    $target->assignRole('creator');
    $artist = Artist::factory()->create(['user_id' => $target->id, 'name' => 'Test Artist']);

    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->firstOrFail();
    Subscription::create([
        'user_id' => $target->id,
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $response = $this->actingAs(moderator(), 'sanctum')->getJson("/api/admin/users/{$target->id}");

    $response->assertOk();
    expect($response->json('subscription.plan'))->toBe('Premium Monthly');
    expect($response->json('artist.name'))->toBe('Test Artist');
    expect($response->json('advertiser'))->toBeNull();
});

test('a moderator can assign and remove a role', function () {
    $target = User::factory()->create();
    $target->assignRole('listener');

    $assign = $this->actingAs(moderator(), 'sanctum')->putJson("/api/admin/users/{$target->id}/roles", [
        'role' => 'creator',
        'action' => 'assign',
    ]);
    $assign->assertOk();
    expect($target->fresh()->hasRole('creator'))->toBeTrue();

    $remove = $this->actingAs(moderator(), 'sanctum')->putJson("/api/admin/users/{$target->id}/roles", [
        'role' => 'creator',
        'action' => 'remove',
    ]);
    $remove->assertOk();
    expect($target->fresh()->hasRole('creator'))->toBeFalse();
});

test('super-admin cannot be assigned or stripped of roles from this endpoint', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs(moderator(), 'sanctum')->putJson("/api/admin/users/{$superAdmin->id}/roles", [
        'role' => 'moderator',
        'action' => 'assign',
    ])->assertForbidden();
});

test('a moderator cannot change their own roles', function () {
    $mod = moderator();

    $this->actingAs($mod, 'sanctum')->putJson("/api/admin/users/{$mod->id}/roles", [
        'role' => 'creator',
        'action' => 'assign',
    ])->assertStatus(422);
});

test('an invalid role is rejected', function () {
    $target = User::factory()->create();

    $this->actingAs(moderator(), 'sanctum')->putJson("/api/admin/users/{$target->id}/roles", [
        'role' => 'super-admin',
        'action' => 'assign',
    ])->assertStatus(422);
});
