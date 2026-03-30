<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiSession;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiSession> */
class AiSessionFactory extends Factory
{
    protected $model = AiSession::class;

    private const TOOLS = ['cursor', 'windsurf', 'claude-code', 'codex', 'copilot-cli'];

    public function definition(): array
    {
        $turns = [];
        for ($i = 0; $i < $this->faker->numberBetween(2, 8); $i++) {
            $turns[] = [
                'role'             => $i % 2 === 0 ? 'user' : 'assistant',
                'content'          => $this->faker->paragraph,
                'timestamp'        => now()->subMinutes(60 - $i * 5)->toIso8601String(),
                'files_referenced' => [],
                'file_diffs'       => [],
                'reasoning_trace'  => null,
                'tool_calls'       => [],
            ];
        }

        $sessionData = [
            'id'                => $this->faker->uuid,
            'title'             => $this->faker->sentence(4),
            'source_tool'       => $this->faker->randomElement(self::TOOLS),
            'source_machine_id' => substr(hash('sha256', $this->faker->uuid), 0, 16),
            'created_at'        => now()->subDays($this->faker->numberBetween(1, 30))->toIso8601String(),
            'last_active_at'    => now()->subHours($this->faker->numberBetween(1, 48))->toIso8601String(),
            'turns'             => $turns,
        ];

        return [
            'project_id'   => Project::factory(),
            'source_tool'  => $sessionData['source_tool'],
            'session_id'   => $sessionData['id'],
            'title'        => $sessionData['title'],
            'session_data' => json_encode($sessionData),
            'exported_at'  => now()->subDays($this->faker->numberBetween(0, 5)),
        ];
    }
}
