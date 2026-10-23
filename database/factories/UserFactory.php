<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\School;
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => StaffRole::Teacher,
            'is_observer' => false,
        ];
    }

    /**
     * Indicate that the user is a teacher at a school.
     */
    public function teacher(): static
    {
        return $this->atSchoolWithRole(StaffRole::Teacher);
    }

    /**
     * Indicate that the user is a school principal (always an observer).
     */
    public function principal(): static
    {
        return $this->atSchoolWithRole(StaffRole::Principal);
    }

    /**
     * Indicate that the user is the school's curriculum head (UTP, always an observer).
     */
    public function curriculumHead(): static
    {
        return $this->atSchoolWithRole(StaffRole::CurriculumHead);
    }

    /**
     * Indicate that the user is flagged as an observer.
     */
    public function observer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_observer' => true,
        ]);
    }

    /**
     * Staff member of a school, inheriting the school's organization.
     */
    protected function atSchoolWithRole(StaffRole $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $role,
            'school_id' => School::factory(),
            'organization_id' => fn (array $attributes): ?int => School::find($attributes['school_id'])?->organization_id,
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
