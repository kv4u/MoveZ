<?php
declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('projects index returns Inertia page', function (): void {
    $response = $this->withoutVite()->get('/projects');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->component('Projects/Index'));
});

it('projects show returns project data', function (): void {
    $user    = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->withoutVite()->get("/projects/{$project->id}");

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->has('project')
            ->has('sessions')
        );
});
