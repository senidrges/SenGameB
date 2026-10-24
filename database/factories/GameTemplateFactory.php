<?php

namespace Database\Factories;

use App\Models\GameTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameTemplate>
 */
class GameTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['Surprise', 'Romantique', 'Fun']),
            'items' => [
                ['number' => 1, 'content' => fake()->sentence()],
                ['number' => 2, 'content' => fake()->sentence()],
            ],
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }
}
