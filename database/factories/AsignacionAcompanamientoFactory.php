<?php

namespace Database\Factories;

use App\Models\AsignacionAcompanamiento;
use Database\Factories\Concerns\CreaFilasDelAnfitrion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires an active establecimiento.
 *
 * @extends Factory<AsignacionAcompanamiento>
 */
class AsignacionAcompanamientoFactory extends Factory
{
    use CreaFilasDelAnfitrion;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'observador_id' => fn (): int => self::nuevoFuncionario(),
            'docente_id' => fn (): int => self::nuevoFuncionario(),
        ];
    }
}
