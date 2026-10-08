<?php
declare(strict_types=1);

use App\Models\AiSession;
use App\Models\Project;
use App\Models\SyncEvent;
use Inertia\Testing\AssertableInertia as Assert;

it('dashboard shows counts and last sync', function (): void {
    AiSession::factory(3)->create();
    SyncEvent::factory()->create();

    $this->withoutVite()->get('/')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.total_sessions', 3)
            ->where('stats.total_projects', 3)
            ->whereNot('stats.last_sync', null)
        );
});

it('session page decodes session data without leaking the raw payload', function (): void {
    $session = AiSession::factory()->create();

    $this->withoutVite()->get("/sessions/{$session->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Show')
            ->has('session.project')
            ->missing('session.session_data')
            ->has('sessionData.turns')
        );
});

it('session page tolerates malformed session data', function (): void {
    $session = AiSession::factory()->create(['session_data' => '"just a string"']);

    $this->withoutVite()->get("/sessions/{$session->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->where('sessionData', []));
});

it('projects index lists session counts without loading sessions', function (): void {
    $project = Project::factory()->create();
    AiSession::factory(2)->create(['project_id' => $project->id]);

    $this->withoutVite()->get('/projects')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->where('projects.data.0.ai_sessions_count', 2)
            ->missing('projects.data.0.ai_sessions')
        );
});
