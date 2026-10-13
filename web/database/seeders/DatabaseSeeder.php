<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::factory(2)->create();

        // Known login for local development and the Playwright suite — never in production
        if (app()->environment(['local', 'testing'])) {
            $users->prepend(User::factory()->create([
                'name'     => 'Demo',
                'email'    => 'demo@movez.test',
                'password' => 'password',
            ]));
            $this->command?->info('Dashboard login: demo@movez.test / password');
        }

        foreach ($users as $user) {
            // Only the hash is stored, so print the plaintext once for local testing
            $token = $user->issueApiToken();
            $this->command?->info("API token for {$user->email}: {$token}");

            $projects = Project::factory(3)->create(['user_id' => $user->id]);

            foreach ($projects as $project) {
                AiSession::factory(5)->create(['project_id' => $project->id]);
            }
        }
    }
}
