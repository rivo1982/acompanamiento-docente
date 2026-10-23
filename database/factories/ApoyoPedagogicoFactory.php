<?php

namespace Database\Factories;

use App\Enums\NivelObjetivoApoyo;
use App\Models\ApoyoPedagogico;
use App\Models\Indicador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApoyoPedagogico>
 */
class ApoyoPedagogicoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicador_id' => Indicador::factory(),
            'posicion' => fake()->numberBetween(1, 3),
            'herramienta' => fake()->words(3, true),
            'nivel_objetivo' => fake()->randomElement(NivelObjetivoApoyo::cases()),
            'como_hacerlo' => fake()->paragraph(),
            'ejemplo' => fake()->paragraph(),
            'duracion' => fake()->randomElement(['5 minutos', '10 minutos', '15 minutos']),
            'senal_de_logro' => fake()->sentence(),
        ];
    }
}
