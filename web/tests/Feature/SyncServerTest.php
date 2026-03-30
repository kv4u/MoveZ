<?php
declare(strict_types=1);

use App\Models\User;

it('sync push returns 401 without a token', function (): void {
    $response = $this->postJson('/api/sync/push', ['data' => 'test']);
    $response->assertStatus(401);
});

it('sync pull returns 401 without a token', function (): void {
    $response = $this->getJson('/api/sync/pull');
    $response->assertStatus(401);
});

it('sync push stores encrypted data with valid token', function (): void {
    $user = User::factory()->create([
        'api_token' => hash('sha256', 'test-token-abc'),
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer test-token-abc'])
        ->postJson('/api/sync/push', ['data' => 'encrypted-blob-xyz']);

    $response->assertStatus(200)->assertJson(['status' => 'ok']);
});

it('sync pull returns data with valid token', function (): void {
    $user = User::factory()->create([
        'api_token' => hash('sha256', 'test-pull-token'),
    ]);

    // Push first
    $this->withHeaders(['Authorization' => 'Bearer test-pull-token'])
        ->postJson('/api/sync/push', ['data' => 'my-encrypted-data']);

    // Then pull
    $response = $this->withHeaders(['Authorization' => 'Bearer test-pull-token'])
        ->getJson('/api/sync/pull');

    $response->assertStatus(200)->assertJson(['data' => 'my-encrypted-data']);
});
