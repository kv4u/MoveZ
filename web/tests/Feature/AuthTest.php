<?php
declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from every dashboard page to the login page', function (string $url): void {
    $this->get($url)->assertRedirect('/login');
})->with(['/', '/projects', '/projects/1', '/sessions/1', '/migration/wizard']);

it('rejects guest migration requests', function (): void {
    $this->postJson('/migration/start', ['from_tool' => 'cursor', 'to_tool' => 'codex'])->assertUnauthorized();
});

it('shows the login page', function (): void {
    $this->withoutVite()->get('/login')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('logs in with valid credentials', function (): void {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function (): void {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('throttles repeated login attempts', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
});

it('logs out', function (): void {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('redirects logged-in users away from the login page', function (): void {
    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/');
});

it('movez:user creates a user that can log in', function (): void {
    $this->artisan('movez:user', ['email' => 'owner@example.com', '--name' => 'Owner'])
        ->expectsQuestion('Password (min 12 characters)', 'long-enough-password')
        ->assertExitCode(0);

    $this->post('/login', ['email' => 'owner@example.com', 'password' => 'long-enough-password'])
        ->assertRedirect('/');
});

it('movez:user rejects short passwords', function (): void {
    $this->artisan('movez:user', ['email' => 'owner@example.com'])
        ->expectsQuestion('Password (min 12 characters)', 'short')
        ->assertExitCode(1);

    expect(User::where('email', 'owner@example.com')->exists())->toBeFalse();
});
