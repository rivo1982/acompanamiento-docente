<?php

namespace Database\Factories;

use App\Models\Indicator;
use App\Models\RubricSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Indicator>
 */
class IndicatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rubric_section_id' => RubricSection::factory(),
            'number' => fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->sentence(4),
            'level_beginning' => fake()->sentence(),
            'level_developing' => fake()->sentence(),
            'level_achieved' => fake()->sentence(),
            'level_outstanding' => fake()->sentence(),
            'feedback_meaning' => fake()->paragraph(),
            'feedback_to_achieved' => fake()->paragraph(),
            'feedback_to_outstanding' => fake()->paragraph(),
            'sources' => fake()->sentence(),
        ];
    }
}
