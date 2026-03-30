<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::factory(2)->create();

        foreach ($users as $user) {
            $user->update(['api_token' => hash('sha256', Str::random(40))]);

            $projects = Project::factory(3)->create(['user_id' => $user->id]);

            foreach ($projects as $project) {
                AiSession::factory(5)->create(['project_id' => $project->id]);
            }
        }
    }
}
