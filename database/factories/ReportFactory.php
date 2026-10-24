<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state: a pending report about an account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reported = User::factory();

        return [
            'reporter_id' => User::factory(),
            'reportable_type' => 'user',
            'reportable_id' => $reported,
            'reported_user_id' => fn (array $attributes) => $attributes['reportable_id'],
            'reason' => ReportReason::Harassment,
            'content_snapshot' => [],
        ];
    }

    /**
     * A report about a given model written by the given user.
     */
    public function about(object $reportable, User $author): static
    {
        return $this->state([
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->getKey(),
            'reported_user_id' => $author->id,
        ]);
    }
}
