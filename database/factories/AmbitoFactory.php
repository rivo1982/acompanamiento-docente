<?php

namespace Database\Factories;

use App\Models\Ambito;
use App\Models\PautaBase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ambito>
 */
class AmbitoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pauta_base_id' => PautaBase::factory(),
            'nombre' => fake()->sentence(3),
            'posicion' => fake()->unique()->numberBetween(1000, 999999),
        ];
    }
}
