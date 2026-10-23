<?php

namespace Database\Seeders;

use App\Models\Ambito;
use App\Models\Indicador;
use App\Models\PautaBase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Carga el catálogo GLOBAL de pautas (pautas base, ámbitos, indicadores) desde catalog.json.
 *
 * Idempotente: las pautas base se identifican por número, los ámbitos por (pauta base, posición)
 * y los indicadores por número. Los ámbitos de una pauta base sembrada que ya no aparecen en el
 * catálogo se borran cuando no les quedan indicadores. El archivo conserva sus claves en inglés
 * (templates, sections, levels...); aquí se mapean a las columnas en español.
 */
class CatalogSeeder extends Seeder
{
    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $catalogo = json_decode(
            file_get_contents(database_path('seeders/data/catalog.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->seedCatalog($catalogo);
    }

    /**
     * Siembra el catálogo dado en una sola transacción.
     *
     * @param  array{templates: list<array<string, mixed>>}  $catalogo
     */
    public function seedCatalog(array $catalogo): void
    {
        DB::transaction(function () use ($catalogo): void {
            foreach ($catalogo['templates'] as $datosPauta) {
                $this->sembrarPautaBase($datosPauta);
            }
        });
    }

    /**
     * @param  array{number: int, name: string, evidence: ?string, standards: ?string, protocol: ?string, usage_warning: ?string, sections: list<array<string, mixed>>}  $datosPauta
     */
    private function sembrarPautaBase(array $datosPauta): void
    {
        $pautaBase = PautaBase::updateOrCreate(
            ['numero' => $datosPauta['number']],
            [
                'nombre' => $datosPauta['name'],
                'evidencia' => $datosPauta['evidence'],
                'estandares' => $datosPauta['standards'],
                'protocolo' => $datosPauta['protocol'],
                'advertencia_uso' => $datosPauta['usage_warning'],
            ],
        );

        foreach ($datosPauta['sections'] as $indice => $datosAmbito) {
            $ambito = Ambito::updateOrCreate(
                ['pauta_base_id' => $pautaBase->id, 'posicion' => $indice + 1],
                ['nombre' => $datosAmbito['name']],
            );

            foreach ($datosAmbito['indicators'] as $datosIndicador) {
                $this->sembrarIndicador($ambito, $datosIndicador);
            }
        }

        $pautaBase->ambitos()
            ->where('posicion', '>', count($datosPauta['sections']))
            ->whereDoesntHave('indicadores')
            ->delete();
    }

    /**
     * @param  array{number: int, name: string, levels: array{0: string, 1: string, 2: string, 3: string}, meaning: string, to_achieved: string, to_outstanding: ?string, sources: string}  $datosIndicador
     */
    private function sembrarIndicador(Ambito $ambito, array $datosIndicador): void
    {
        Indicador::updateOrCreate(
            ['numero' => $datosIndicador['number']],
            [
                'ambito_id' => $ambito->id,
                'nombre' => $datosIndicador['name'],
                'nivel_en_inicio' => $datosIndicador['levels'][0],
                'nivel_en_desarrollo' => $datosIndicador['levels'][1],
                'nivel_logrado' => $datosIndicador['levels'][2],
                'nivel_destacado' => $datosIndicador['levels'][3],
                'retro_que_significa' => $datosIndicador['meaning'],
                'retro_para_logrado' => $datosIndicador['to_achieved'],
                'retro_para_destacado' => $datosIndicador['to_outstanding'],
                'fuentes' => $datosIndicador['sources'],
            ],
        );
    }
}
