<?php

namespace Database\Factories;

use App\Models\PautaBase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PautaBase>
 */
class PautaBaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numberBetween(1000, 999999),
            'nombre' => 'Pauta '.fake()->words(3, true),
            'evidencia' => fake()->paragraph(),
            'estandares' => fake()->paragraph(),
            'protocolo' => fake()->paragraph(),
            'advertencia_uso' => fake()->sentence(),
        ];
    }
}
