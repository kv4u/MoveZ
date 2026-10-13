<?php
declare(strict_types=1);

use App\Models\AiSession;
use App\Models\Project;
use App\Models\SyncEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('dashboard shows the user\'s own counts and last sync', function (): void {
    $user    = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    AiSession::factory(3)->create(['project_id' => $project->id]);
    SyncEvent::factory()->create(['user_id' => $user->id]);

    // Someone else's data must not be counted
    AiSession::factory(5)->create();

    $this->actingAs($user)->withoutVite()->get('/')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.total_sessions', 3)
            ->where('stats.total_projects', 1)
            ->whereNot('stats.last_sync', null)
            ->where('auth.user.email', $user->email)
        );
});

it('session page decodes session data without leaking the raw payload', function (): void {
    $user    = User::factory()->create();
    $session = AiSession::factory()->create([
        'project_id' => Project::factory()->create(['user_id' => $user->id])->id,
    ]);

    $this->actingAs($user)->withoutVite()->get("/sessions/{$session->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Show')
            ->has('session.project')
            ->missing('session.session_data')
            ->has('sessionData.turns')
        );
});

it('session page tolerates malformed session data', function (): void {
    $user    = User::factory()->create();
    $session = AiSession::factory()->create([
        'project_id'   => Project::factory()->create(['user_id' => $user->id])->id,
        'session_data' => '"just a string"',
    ]);

    $this->actingAs($user)->withoutVite()->get("/sessions/{$session->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->where('sessionData', []));
});

it('another user\'s session is not found', function (): void {
    $session = AiSession::factory()->create();

    $this->actingAs(User::factory()->create())->withoutVite()
        ->get("/sessions/{$session->id}")
        ->assertNotFound();
});

it('projects index lists session counts without loading sessions', function (): void {
    $user    = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    AiSession::factory(2)->create(['project_id' => $project->id]);

    $this->actingAs($user)->withoutVite()->get('/projects')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->where('projects.data.0.ai_sessions_count', 2)
            ->missing('projects.data.0.ai_sessions')
        );
});
