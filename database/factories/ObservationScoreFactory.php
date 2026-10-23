<?php

namespace Database\Factories;

use App\Enums\AchievementLevel;
use App\Models\Indicator;
use App\Models\Observation;
use App\Models\ObservationScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObservationScore>
 */
class ObservationScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'observation_id' => Observation::factory(),
            'indicator_id' => Indicator::factory(),
            'level' => fake()->randomElement(AchievementLevel::cases()),
            'personal_feedback' => null,
        ];
    }

    /**
     * Indicate that the observer wrote a personal comment.
     */
    public function withPersonalFeedback(): static
    {
        return $this->state(fn (array $attributes): array => [
            'personal_feedback' => fake()->paragraph(),
        ]);
    }
}
