<?php
declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('migration wizard returns Inertia page', function (): void {
    $response = $this->withoutVite()->get('/migration/wizard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Migration/Wizard')
            ->has('supportedTools')
            ->has('projects')
        );
});

it('migration start requires from_tool and to_tool', function (): void {
    $response = $this->postJson('/migration/start', []);
    $response->assertStatus(422);
});

it('migration start with valid data returns queued status', function (): void {
    $response = $this->postJson('/migration/start', [
        'from_tool' => 'cursor',
        'to_tool'   => 'claude-code',
    ]);

    $response->assertStatus(200)->assertJson(['status' => 'queued']);
});
