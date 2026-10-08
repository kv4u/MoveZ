<?php
declare(strict_types=1);

use App\Models\AiSession;
use App\Models\User;

function syncUser(string $token): User
{
    $user = User::factory()->create();
    $user->forceFill(['api_token' => hash('sha256', $token)])->save();

    return $user;
}

it('sync push returns 401 without a token', function (): void {
    $this->postJson('/api/sync/push', ['sessions' => 'test'])->assertStatus(401);
});

it('sync pull returns 401 without a token', function (): void {
    $this->getJson('/api/sync/pull')->assertStatus(401);
});

it('sync rejects an unknown token', function (): void {
    syncUser('real-token');

    $this->withToken('wrong-token')->getJson('/api/sync/pull')->assertStatus(401);
});

it('sync push requires a sessions payload', function (): void {
    syncUser('tok');

    $this->withToken('tok')->postJson('/api/sync/push', ['data' => 'old-key'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('sessions');
});

it('sync push stores the encrypted blob and records the event', function (): void {
    $user = syncUser('test-token-abc');

    $this->withToken('test-token-abc')
        ->postJson('/api/sync/push', ['sessions' => 'encrypted-blob-xyz', 'count' => 3])
        ->assertStatus(200)
        ->assertJson(['status' => 'ok', 'count' => 3]);

    $this->assertDatabaseHas('sync_blobs', ['user_id' => $user->id, 'payload' => 'encrypted-blob-xyz', 'session_count' => 3]);
    $this->assertDatabaseHas('sync_events', ['user_id' => $user->id, 'event_type' => 'push', 'session_count' => 3]);

    // Sync payloads are not browsable sessions
    expect(AiSession::count())->toBe(0);
});

it('sync pull returns the latest blob for the same user', function (): void {
    syncUser('test-pull-token');

    $this->withToken('test-pull-token')->postJson('/api/sync/push', ['sessions' => 'first', 'count' => 1]);
    $this->withToken('test-pull-token')->postJson('/api/sync/push', ['sessions' => 'second', 'count' => 2]);

    $this->withToken('test-pull-token')->getJson('/api/sync/pull')
        ->assertStatus(200)
        ->assertExactJson(['sessions' => 'second', 'count' => 2]);
});

it('sync pull returns null sessions before anything was pushed', function (): void {
    syncUser('fresh');

    $this->withToken('fresh')->getJson('/api/sync/pull')
        ->assertStatus(200)
        ->assertExactJson(['sessions' => null, 'count' => 0]);
});

it('users cannot pull each other\'s data', function (): void {
    syncUser('alice');
    syncUser('bob');

    $this->withToken('alice')->postJson('/api/sync/push', ['sessions' => 'alice-secret']);

    $this->withToken('bob')->getJson('/api/sync/pull')
        ->assertExactJson(['sessions' => null, 'count' => 0]);
});

it('api token is never serialized', function (): void {
    $user = syncUser('hidden');

    expect($user->toArray())->not->toHaveKey('api_token');
});

it('movez:token issues a working token', function (): void {
    $user = User::factory()->create(['email' => 'dev@example.com']);

    $this->artisan('movez:token', ['email' => 'dev@example.com'])->assertExitCode(0);

    expect($user->fresh()->api_token)->toHaveLength(64);
});
