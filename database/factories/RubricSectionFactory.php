<?php

namespace Database\Factories;

use App\Models\RubricSection;
use App\Models\RubricTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RubricSection>
 */
class RubricSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rubric_template_id' => RubricTemplate::factory(),
            'name' => fake()->words(3, true),
            'position' => fake()->numberBetween(1, 5),
        ];
    }
}
