<?php

namespace Database\Factories;

use App\Models\GameChoice;
use App\Models\LoveGame;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameChoice>
 */
class GameChoiceFactory extends Factory
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
            'user_id' => User::factory(),
            'choice_number' => 1,
            'chosen_at' => now(),
        ];
    }
}
