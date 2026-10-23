<?php

namespace Database\Factories;

use App\Models\Observador;
use Database\Factories\Concerns\CreaFilasDelAnfitrion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires an active establecimiento.
 *
 * @extends Factory<Observador>
 */
class ObservadorFactory extends Factory
{
    use CreaFilasDelAnfitrion;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funcionario_id' => fn (): int => self::nuevoFuncionario(),
        ];
    }
}
