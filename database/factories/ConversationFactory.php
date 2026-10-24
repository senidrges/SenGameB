<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }

    /**
     * A one-to-one conversation between the two users.
     */
    public function between(User $first, User $second): static
    {
        return $this
            ->state(['direct_key' => Conversation::directKeyFor($first, $second)])
            ->afterCreating(fn (Conversation $conversation) => $conversation->participants()->attach([$first->id, $second->id]));
    }
}
