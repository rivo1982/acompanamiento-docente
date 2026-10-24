<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Funciones genéricas del catálogo global, una por categoría, a las que la carga de funcionarios (Q18) asigna a
 * cada persona invitada según la función de la planilla. Propuesta para Vinkured (junto a su FuncionesBaseSeeder):
 * datos de referencia, corre en todos los entornos y es idempotente (upsert por `clave`).
 */
class FuncionesGenericasSeeder extends Seeder
{
    /**
     * @return list<array{clave: string, nombre: string, categoria: string}>
     */
    public static function funciones(): array
    {
        return [
            ['clave' => 'docente', 'nombre' => 'Docente', 'categoria' => 'docente'],
            ['clave' => 'directivo', 'nombre' => 'Directivo', 'categoria' => 'directivo'],
            ['clave' => 'asistente', 'nombre' => 'Asistente de la educación', 'categoria' => 'asistente'],
            ['clave' => 'profesional_apoyo', 'nombre' => 'Profesional de apoyo', 'categoria' => 'profesional_apoyo'],
        ];
    }

    public function run(): void
    {
        $ahora = now();

        DB::table('funciones')->upsert(
            array_map(fn (array $f): array => $f + ['created_at' => $ahora, 'updated_at' => $ahora], self::funciones()),
            ['clave'],
            ['nombre', 'categoria', 'updated_at'],
        );
    }
}
