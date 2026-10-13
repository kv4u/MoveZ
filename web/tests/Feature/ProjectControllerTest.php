<?php
declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('projects index returns Inertia page', function (): void {
    $this->actingAs(User::factory()->create())
        ->withoutVite()->get('/projects')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->component('Projects/Index'));
});

it('projects index only lists the user\'s own projects', function (): void {
    $user = User::factory()->create();
    Project::factory()->create(['user_id' => $user->id, 'name' => 'Mine']);
    Project::factory()->create(['name' => 'Theirs']);

    $this->actingAs($user)->withoutVite()->get('/projects')
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Mine')
        );
});

it('projects show returns project data', function (): void {
    $user    = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->withoutVite()->get("/projects/{$project->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->has('project')
            ->has('sessions')
        );
});

it('another user\'s project is not found', function (): void {
    $project = Project::factory()->create();

    $this->actingAs(User::factory()->create())->withoutVite()
        ->get("/projects/{$project->id}")
        ->assertNotFound();
});
