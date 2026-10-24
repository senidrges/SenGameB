<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: a user who completed onboarding.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'username' => fake()->unique()->regexify('[a-z]{6}[0-9]{3}'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone_number' => '+2267'.fake()->unique()->numerify('#######'),
            'first_login_completed' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A freshly registered account that has not completed onboarding.
     */
    public function withoutProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'username' => null,
            'first_login_completed' => false,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function withPushToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
