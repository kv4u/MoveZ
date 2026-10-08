<?php
declare(strict_types=1);

use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;

it('migration wizard returns Inertia page', function (): void {
    $response = $this->withoutVite()->get('/migration/wizard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Migration/Wizard')
            ->has('supportedTools')
            ->has('writableTools')
            ->has('projects')
        );
});

it('migration start requires from_tool and to_tool', function (): void {
    $this->postJson('/migration/start', [])->assertStatus(422);
});

it('migration start rejects tools the CLI cannot write to', function (): void {
    $this->postJson('/migration/start', ['from_tool' => 'cursor', 'to_tool' => 'cline'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('to_tool');
});

it('migration start returns the CLI command to run', function (): void {
    $project = Project::factory()->create(['path' => '/home/dev/my app']);

    $this->postJson('/migration/start', [
        'from_tool'  => 'cursor',
        'to_tool'    => 'claude-code',
        'project_id' => $project->id,
        'from_path'  => '/old/home',
        'to_path'    => '/home/dev',
    ])
        ->assertStatus(200)
        ->assertJson([
            'status'  => 'ready',
            'command' => 'movez transfer --from=cursor --to=claude-code --project="/home/dev/my app" --from-path=/old/home --to-path=/home/dev',
        ]);
});
