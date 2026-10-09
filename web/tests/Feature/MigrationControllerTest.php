<?php
declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('migration wizard returns Inertia page with the user\'s projects', function (): void {
    Project::factory()->create(['user_id' => $this->user->id]);
    Project::factory()->create();   // someone else's

    $this->withoutVite()->get('/migration/wizard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Migration/Wizard')
            ->has('supportedTools')
            ->has('writableTools')
            ->has('projects', 1)
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

it('migration start rejects another user\'s project', function (): void {
    $project = Project::factory()->create();

    $this->postJson('/migration/start', ['from_tool' => 'cursor', 'to_tool' => 'codex', 'project_id' => $project->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('project_id');
});

it('migration start returns the CLI command to run', function (): void {
    $project = Project::factory()->create(['user_id' => $this->user->id, 'path' => '/home/dev/my app']);

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
