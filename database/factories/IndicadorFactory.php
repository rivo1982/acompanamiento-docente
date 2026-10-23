<?php

namespace Database\Factories;

use App\Models\Ambito;
use App\Models\Indicador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Indicador>
 */
class IndicadorFactory extends Factory
{
    /**
     * Numbers start above the 60 catalog indicators so factories never collide with the seeder.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ambito_id' => Ambito::factory(),
            'numero' => fake()->unique()->numberBetween(1000, 999999),
            'nombre' => fake()->sentence(4),
            'nivel_en_inicio' => fake()->sentence(),
            'nivel_en_desarrollo' => fake()->sentence(),
            'nivel_logrado' => fake()->sentence(),
            'nivel_destacado' => fake()->sentence(),
            'retro_que_significa' => fake()->paragraph(),
            'retro_para_logrado' => fake()->paragraph(),
            'retro_para_destacado' => fake()->paragraph(),
            'fuentes' => fake()->sentence(),
        ];
    }
}
