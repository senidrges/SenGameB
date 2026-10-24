<?php

namespace Database\Factories;

use App\Models\GameItem;
use App\Models\LoveGame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameItem>
 */
class GameItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => LoveGame::factory(),
            'number' => fake()->unique()->numberBetween(1, 999),
            'content' => fake()->sentence(),
        ];
    }
}
