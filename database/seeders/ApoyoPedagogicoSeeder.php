<?php

namespace Database\Seeders;

use App\Models\ApoyoPedagogico;
use App\Models\Indicador;
use App\Support\ApoyoPedagogico\Orientaciones;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

/**
 * Carga las fichas del anexo de apoyo pedagógico (apoyo_pedagogico.json), una por indicador del catálogo GLOBAL.
 *
 * Idempotente: cada ficha se identifica por el número de su indicador y vuelve a los textos del archivo. Exige que
 * el catálogo ya esté sembrado (CatalogSeeder la llama al final): una ficha sin indicador detiene la siembra
 * completa. Las orientaciones generales no se guardan: se leen del archivo (Orientaciones).
 */
class ApoyoPedagogicoSeeder extends Seeder
{
    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $anexo = json_decode((string) file_get_contents(Orientaciones::archivo()), true, flags: JSON_THROW_ON_ERROR);

        $this->seedFichas($anexo['fichas']);
    }

    /**
     * Siembra las fichas dadas en una sola transacción.
     *
     * @param  list<array{numero: int, nombre: string, contexto: string, foco_mejora: string, accion_metodologica: string, accion_procedimental: string, accion_formativa: string, evidencia_avance: string, referencias: string}>  $fichas
     *
     * @throws RuntimeException si el número de una ficha no tiene indicador en el catálogo
     */
    public function seedFichas(array $fichas): void
    {
        $indicadores = Indicador::query()->pluck('id', 'numero');

        DB::transaction(function () use ($fichas, $indicadores): void {
            foreach ($fichas as $ficha) {
                $indicadorId = $indicadores[$ficha['numero']]
                    ?? throw new RuntimeException("La ficha de apoyo pedagógico {$ficha['numero']} no tiene indicador en el catálogo.");

                ApoyoPedagogico::updateOrCreate(['indicador_id' => $indicadorId], [
                    'contexto' => $ficha['contexto'],
                    'foco_mejora' => $ficha['foco_mejora'],
                    'accion_metodologica' => $ficha['accion_metodologica'],
                    'accion_procedimental' => $ficha['accion_procedimental'],
                    'accion_formativa' => $ficha['accion_formativa'],
                    'evidencia_avance' => $ficha['evidencia_avance'],
                    'referencias' => $ficha['referencias'],
                ]);
            }
        });
    }
}
