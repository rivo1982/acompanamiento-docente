<?php

namespace Database\Factories;

use App\Models\Pauta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires an active establecimiento: the model takes establecimiento_id from it.
 *
 * @extends Factory<Pauta>
 */
class PautaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Pauta '.fake()->words(2, true),
        ];
    }
}
