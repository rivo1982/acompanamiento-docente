<?php

namespace Database\Factories;

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
            'contexto' => fake()->sentence(),
            'foco_mejora' => fake()->paragraph(),
            'accion_metodologica' => fake()->paragraph(),
            'accion_procedimental' => fake()->paragraph(),
            'accion_formativa' => fake()->paragraph(),
            'evidencia_avance' => fake()->sentence(),
            'referencias' => fake()->sentence(),
        ];
    }
}
