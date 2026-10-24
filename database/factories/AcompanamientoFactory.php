<?php

namespace Database\Factories;

use App\Enums\EstadoAcompanamiento;
use App\Models\Acompanamiento;
use App\Models\Pauta;
use Database\Factories\Concerns\CreaFilasDelAnfitrion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires an active establecimiento.
 *
 * @extends Factory<Acompanamiento>
 */
class AcompanamientoFactory extends Factory
{
    use CreaFilasDelAnfitrion;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pauta_id' => Pauta::factory(),
            'observador_id' => fn (): int => self::nuevoFuncionario(),
            'docente_id' => fn (): int => self::nuevoFuncionario(),
            'curso_id' => fn (): int => self::nuevoCurso(),
            'asignatura_id' => fn (): int => self::nuevaAsignatura(),
            'iniciado_en' => now(),
        ];
    }

    /**
     * Acompañamiento ya cerrado. Las fábricas no respetan $fillable; el código de la aplicación
     * cierra con finalizar().
     */
    public function finalizado(int $duracionMinutos = 45): static
    {
        return $this->state(fn (array $atributos): array => [
            'iniciado_en' => now()->subMinutes($duracionMinutos),
            'finalizado_en' => now(),
            'duracion_minutos' => $duracionMinutos,
            'estado' => EstadoAcompanamiento::Finalizado,
        ]);
    }
}
