<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Escuela '.fake()->lastName(),
            'rbd' => fake()->unique()->numerify('#####-#'),
        ];
    }
}
