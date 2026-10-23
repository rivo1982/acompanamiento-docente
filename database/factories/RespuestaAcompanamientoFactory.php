<?php

namespace Database\Factories;

use App\Enums\NivelLogro;
use App\Models\Acompanamiento;
use App\Models\Indicador;
use App\Models\RespuestaAcompanamiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires an active establecimiento.
 *
 * @extends Factory<RespuestaAcompanamiento>
 */
class RespuestaAcompanamientoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'acompanamiento_id' => Acompanamiento::factory(),
            'indicador_id' => Indicador::factory(),
            'nivel' => fake()->randomElement(NivelLogro::cases()),
            'retroalimentacion_personal' => fake()->optional()->sentence(),
        ];
    }
}
