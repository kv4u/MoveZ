<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name'    => $this->faker->words(3, true),
            'path'    => '/Users/' . $this->faker->userName . '/projects/' . $this->faker->slug,
        ];
    }
}
