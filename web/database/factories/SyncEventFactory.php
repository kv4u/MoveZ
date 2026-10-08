<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\SyncEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SyncEvent> */
class SyncEventFactory extends Factory
{
    protected $model = SyncEvent::class;

    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'event_type'    => $this->faker->randomElement(['push', 'pull']),
            'session_count' => $this->faker->numberBetween(0, 50),
            'source_tool'   => $this->faker->randomElement(['cursor', 'claude-code', 'codex', null]),
            'created_at'    => now(),
        ];
    }
}
