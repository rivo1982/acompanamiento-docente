<?php

/*
 * The apoyo pedagógico annex (database/seeders/data/apoyo_pedagogico.json): one ficha per catalog
 * indicador, seeded together with the catalog, plus its general guidance (Orientaciones).
 */

use App\Models\ApoyoPedagogico;
use App\Models\Indicador;
use App\Support\ApoyoPedagogico\Orientaciones;
use Database\Seeders\ApoyoPedagogicoSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const CAMPOS_DE_LA_FICHA = [
    'contexto', 'foco_mejora', 'accion_metodologica', 'accion_procedimental', 'accion_formativa', 'evidencia_avance', 'referencias',
];

/** @return array{orientaciones: array<string, mixed>, fichas: list<array<string, mixed>>} */
function anexoJson(): array
{
    return json_decode(file_get_contents(database_path('seeders/data/apoyo_pedagogico.json')), true);
}

it('siembra con el catálogo una ficha por cada uno de los sesenta indicadores', function () {
    $this->seed(CatalogSeeder::class);

    $numeros = ApoyoPedagogico::query()->with('indicador')->get()->map(fn (ApoyoPedagogico $ficha): int => $ficha->indicador->numero);

    expect(ApoyoPedagogico::count())->toBe(60)
        ->and($numeros->sort()->values()->all())->toBe(range(1, 60));
});

it('trae en el archivo sesenta fichas numeradas del 1 al 60 con el nombre de su indicador del catálogo', function () {
    $this->seed(CatalogSeeder::class);
    $fichas = collect(anexoJson()['fichas']);

    expect($fichas)->toHaveCount(60)
        ->and($fichas->pluck('numero')->sort()->values()->all())->toBe(range(1, 60))
        ->and($fichas->pluck('nombre', 'numero')->sortKeys()->all())
        ->toBe(Indicador::query()->orderBy('numero')->pluck('nombre', 'numero')->all());
});

it('copia los siete textos de cada ficha sin dejar ninguno vacío', function () {
    $this->seed(CatalogSeeder::class);
    $fuente = collect(anexoJson()['fichas'])->firstWhere('numero', 42);

    $ficha = Indicador::where('numero', 42)->firstOrFail()->apoyoPedagogico;

    expect($ficha->only(CAMPOS_DE_LA_FICHA))->toBe(array_intersect_key($fuente, array_flip(CAMPOS_DE_LA_FICHA)));
    foreach (ApoyoPedagogico::all() as $cualquiera) {
        foreach (CAMPOS_DE_LA_FICHA as $campo) {
            expect(trim((string) $cualquiera->{$campo}))->not->toBe('', "Ficha {$cualquiera->indicador_id}: {$campo} vacío");
        }
    }
});

it('no duplica las fichas y restaura los textos editados al volver a sembrar', function () {
    $this->seed(CatalogSeeder::class);
    $ficha = Indicador::where('numero', 1)->firstOrFail()->apoyoPedagogico;
    $ficha->update(['foco_mejora' => 'Editado']);

    $this->seed(CatalogSeeder::class);

    expect(ApoyoPedagogico::count())->toBe(60)
        ->and($ficha->fresh()->foco_mejora)->toBe(collect(anexoJson()['fichas'])->firstWhere('numero', 1)['foco_mejora']);
});

it('falla en voz alta si una ficha no tiene indicador en el catálogo', function () {
    $this->seed(CatalogSeeder::class);
    $anexo = anexoJson();
    $anexo['fichas'][0]['numero'] = 999;

    expect(fn () => (new ApoyoPedagogicoSeeder)->seedFichas($anexo['fichas']))->toThrow(RuntimeException::class, '999');
    expect(ApoyoPedagogico::count())->toBe(60);
});

it('deshace las fichas ya escritas cuando una ficha posterior no tiene indicador', function () {
    $this->seed(CatalogSeeder::class);
    ApoyoPedagogico::query()->delete();
    $anexo = anexoJson();
    $anexo['fichas'][array_key_last($anexo['fichas'])]['numero'] = 999;

    expect(fn () => (new ApoyoPedagogicoSeeder)->seedFichas($anexo['fichas']))->toThrow(RuntimeException::class, '999');
    expect(ApoyoPedagogico::count())->toBe(0);
});

it('expone las orientaciones generales del anexo', function () {
    $fuente = anexoJson()['orientaciones'];
    $orientaciones = Orientaciones::delAnexo();

    expect($orientaciones->comoUsar())->toBe($fuente['como_usar'])
        ->and($orientaciones->cicloSugerido())->toBe($fuente['ciclo_sugerido'])->toHaveCount(4)
        ->and($orientaciones->fundamentoCiclo())->toBe($fuente['fundamento_ciclo'])
        ->and($orientaciones->notaDeUso())->toBe($fuente['nota_de_uso'])
        ->and($orientaciones->notaProtocolo())->toBe($fuente['nota_protocolo'])
        ->and($orientaciones->planDeMejora())->toBe($fuente['plan_de_mejora']);
});

it('exige el protocolo solo en los descriptores 42 y 45', function () {
    $orientaciones = Orientaciones::delAnexo();

    expect(array_values(array_filter(range(1, 60), $orientaciones->requiereProtocolo(...))))->toBe([42, 45]);
});
