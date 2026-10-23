<?php

namespace Database\Factories;

use App\Enums\TeachingTipTarget;
use App\Models\Indicator;
use App\Models\TeachingTip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingTip>
 */
class TeachingTipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_id' => Indicator::factory(),
            'position' => fake()->numberBetween(1, 3),
            'tool_name' => fake()->words(2, true),
            'target' => fake()->randomElement(TeachingTipTarget::cases()),
            'how_to' => fake()->paragraph(),
            'example' => fake()->paragraph(),
            'duration' => fake()->numberBetween(5, 20).' minutos',
            'success_signal' => fake()->sentence(),
        ];
    }
}
