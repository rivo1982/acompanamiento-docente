<?php

namespace Database\Factories;

use App\Enums\ObservationStatus;
use App\Models\Observation;
use App\Models\Rubric;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observation>
 */
class ObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rubric_id' => Rubric::factory(),
            'school_id' => fn (array $attributes): int => Rubric::findOrFail($attributes['rubric_id'])->school_id,
            'observer_id' => fn (array $attributes): int => User::factory()->principal()->create(['school_id' => $attributes['school_id']])->id,
            'teacher_id' => fn (array $attributes): int => User::factory()->teacher()->create(['school_id' => $attributes['school_id']])->id,
            'subject' => fake()->randomElement(['Lenguaje', 'Matemática', 'Historia', 'Ciencias Naturales']),
            'education_level' => fake()->randomElement(['Educación Básica', 'Educación Media']),
            'course' => fake()->randomElement(['1° Básico', '4° Básico', '8° Básico', '2° Medio']),
            'started_at' => now(),
            'status' => ObservationStatus::InProgress,
        ];
    }

    /**
     * Indicate that the observation has already finished.
     */
    public function finished(int $durationMinutes = 45): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_at' => now()->subMinutes($durationMinutes),
            'finished_at' => now(),
            'duration_minutes' => $durationMinutes,
            'status' => ObservationStatus::Finished,
        ]);
    }
}
