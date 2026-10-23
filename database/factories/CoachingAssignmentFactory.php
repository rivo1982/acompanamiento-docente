<?php

namespace Database\Factories;

use App\Models\CoachingAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachingAssignment>
 */
class CoachingAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'observer_id' => fn (array $attributes): int => User::factory()->principal()->create(['school_id' => $attributes['school_id']])->id,
            'teacher_id' => fn (array $attributes): int => User::factory()->teacher()->create(['school_id' => $attributes['school_id']])->id,
        ];
    }
}
