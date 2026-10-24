<?php

namespace Database\Factories;

use App\Models\AsignacionAcompanamiento;
use Database\Factories\Concerns\CreaFilasDelAnfitrion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires an active establecimiento. By default the pair is eligible: a flagged observer and a
 * funcionario with a docente función.
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
            'observador_id' => fn (): int => self::nuevoObservador(),
            'docente_id' => fn (): int => self::nuevoFuncionarioConFuncion('docente'),
        ];
    }
}
