<?php

namespace Database\Factories;

use App\Enums\NivelLogro;
use App\Models\Acompanamiento;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\RespuestaAcompanamiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires an active establecimiento. By default the acompanamiento's pauta has one indicador and
 * the respuesta answers it, so the write guard (indicador frozen in the snapshot) is satisfied.
 * With for($acompanamiento), pass an indicador_id from that acompanamiento's snapshot.
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
            'acompanamiento_id' => Acompanamiento::factory()->for(
                Pauta::factory()->hasAttached(Indicador::factory(), ['posicion' => 1], 'indicadores'),
            ),
            'indicador_id' => fn (array $atributos): int => Acompanamiento::query()
                ->findOrFail($atributos['acompanamiento_id'])
                ->pauta_snapshot['indicadores'][0]['id'],
            'nivel' => fake()->randomElement(NivelLogro::cases()),
            'retroalimentacion_personal' => fake()->optional()->sentence(),
        ];
    }
}
