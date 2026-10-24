<?php

namespace Database\Factories;

use App\Enums\GameRole;
use App\Enums\GameStatus;
use App\Models\LoveGame;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoveGame>
 */
class LoveGameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organizer_id' => User::factory(),
            'title' => fake()->words(3, true),
            'status' => GameStatus::Waiting,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => GameStatus::Active]);
    }

    /**
     * Register the organizer and the given players as participants, with
     * items numbered from 1 to $itemCount.
     *
     * @param  list<User>  $players
     */
    public function withPlayers(array $players, int $itemCount = 3): static
    {
        return $this->afterCreating(function (LoveGame $game) use ($players, $itemCount): void {
            $game->participants()->attach($game->organizer_id, ['role' => GameRole::Organizer->value]);

            foreach ($players as $player) {
                $game->participants()->attach($player->id, ['role' => GameRole::Player->value]);
            }

            foreach (range(1, $itemCount) as $number) {
                $game->items()->create(['number' => $number, 'content' => fake()->sentence()]);
            }
        });
    }
}
