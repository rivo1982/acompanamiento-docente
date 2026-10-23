<?php

namespace Database\Factories;

use App\Models\RubricTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RubricTemplate>
 */
class RubricTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1000, 999999),
            'name' => 'Pauta '.fake()->words(3, true),
            'evidence' => fake()->paragraph(),
            'standards' => fake()->paragraph(),
            'protocol' => fake()->paragraph(),
            'usage_warning' => fake()->sentence(),
        ];
    }
}
