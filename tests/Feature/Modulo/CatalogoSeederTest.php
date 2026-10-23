<?php

/*
 * Ported from tests/Feature/Seeders/CatalogSeederTest.php (commit b3397fc) to the Spanish
 * catalog tables. The catalog is global: no establecimiento is needed to seed or read it.
 */

use App\Models\Ambito;
use App\Models\ApoyoPedagogico;
use App\Models\Indicador;
use App\Models\PautaBase;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{templates: list<array<string, mixed>>} */
function catalogoJson(): array
{
    return json_decode(file_get_contents(database_path('seeders/data/catalog.json')), true);
}

/** @return array<string, mixed> */
function indicadorDelCatalogo(int $numero): array
{
    return collect(catalogoJson()['templates'])
        ->flatMap(fn (array $pauta): array => $pauta['sections'])
        ->flatMap(fn (array $ambito): array => $ambito['indicators'])
        ->firstWhere('number', $numero);
}

it('siembra diez pautas base y sesenta indicadores numerados', function () {
    $this->seed(CatalogSeeder::class);

    expect(PautaBase::count())->toBe(10)
        ->and(PautaBase::orderBy('numero')->pluck('numero')->all())->toBe(range(1, 10))
        ->and(Indicador::orderBy('numero')->pluck('numero')->all())->toBe(range(1, 60))
        ->and(ApoyoPedagogico::count())->toBe(0);
});

it('conserva los ámbitos de la primera pauta en el orden del archivo', function () {
    $this->seed(CatalogSeeder::class);

    $pauta = PautaBase::where('numero', 1)->firstOrFail();

    expect($pauta->ambitos()->pluck('nombre')->all())->toBe([
        'Ambiente propicio para el aprendizaje',
        'Desarrollo personal y social',
        'Enseñanza para el aprendizaje de todos los estudiantes',
        'Evaluación y retroalimentación en el aula',
    ])->and($pauta->ambitos()->pluck('posicion')->all())->toBe([1, 2, 3, 4]);
});

it('copia los textos del indicador desde el archivo del catálogo', function () {
    $this->seed(CatalogSeeder::class);

    $fuente = indicadorDelCatalogo(1);
    $indicador = Indicador::where('numero', 1)->firstOrFail();

    expect($indicador->nombre)->toBe('Trato respetuoso e inclusivo')
        ->and($indicador->nivel_en_inicio)->toBe($fuente['levels'][0])
        ->and($indicador->nivel_en_desarrollo)->toBe($fuente['levels'][1])
        ->and($indicador->nivel_logrado)->toBe($fuente['levels'][2])
        ->and($indicador->nivel_destacado)->toBe($fuente['levels'][3])
        ->and($indicador->retro_que_significa)->toBe($fuente['meaning'])
        ->and($indicador->retro_para_logrado)->toBe($fuente['to_achieved'])
        ->and($indicador->retro_para_destacado)->toBe($fuente['to_outstanding'])
        ->and($indicador->fuentes)->toBe($fuente['sources'])
        ->and($indicador->ambito->nombre)->toBe('Ambiente propicio para el aprendizaje');
});

it('guarda la advertencia de uso de la pauta de ética', function () {
    $this->seed(CatalogSeeder::class);

    expect(PautaBase::where('numero', 6)->value('advertencia_uso'))->not->toBeNull();
});

it('no duplica el catálogo al sembrar dos veces', function () {
    $this->seed(CatalogSeeder::class);
    $ambitos = Ambito::count();

    $this->seed(CatalogSeeder::class);

    expect(PautaBase::count())->toBe(10)
        ->and(Ambito::count())->toBe($ambitos)
        ->and(Indicador::count())->toBe(60);
});

it('restaura los textos editados al volver a sembrar', function () {
    $this->seed(CatalogSeeder::class);
    Indicador::where('numero', 1)->update(['nombre' => 'Editado']);

    $this->seed(CatalogSeeder::class);

    expect(Indicador::where('numero', 1)->value('nombre'))->toBe('Trato respetuoso e inclusivo');
});

it('las fábricas no chocan con los números del catálogo sembrado', function () {
    $this->seed(CatalogSeeder::class);

    $ambito = Ambito::factory()->create();
    PautaBase::factory()->count(9)->create();
    Indicador::factory()->count(60)->for($ambito)->create();

    expect(PautaBase::count())->toBe(20)
        ->and(Indicador::count())->toBe(120);
});

it('actualiza en su lugar un ámbito renombrado sin dejar huérfanos', function () {
    $this->seed(CatalogSeeder::class);
    $ambito = PautaBase::where('numero', 1)->firstOrFail()->ambitos()->firstOrFail();
    $indicadores = $ambito->indicadores()->pluck('id')->all();
    $ambitos = Ambito::count();
    $catalogo = catalogoJson();
    $catalogo['templates'][0]['sections'][0]['name'] = 'Ámbito renombrado';

    (new CatalogSeeder)->seedCatalog($catalogo);

    expect(Ambito::count())->toBe($ambitos)
        ->and($ambito->fresh()->nombre)->toBe('Ámbito renombrado')
        ->and($ambito->indicadores()->pluck('id')->all())->toBe($indicadores)
        ->and(Ambito::where('nombre', 'Ambiente propicio para el aprendizaje')->exists())->toBeFalse();
});

it('borra los ámbitos que quedan vacíos cuando sus indicadores se mueven', function () {
    $this->seed(CatalogSeeder::class);
    $pauta = PautaBase::where('numero', 1)->firstOrFail();
    $ultimo = $pauta->ambitos()->get()->last();
    $movidos = $ultimo->indicadores()->pluck('id')->all();
    $catalogo = catalogoJson();
    $quitado = array_pop($catalogo['templates'][0]['sections']);
    array_push($catalogo['templates'][0]['sections'][2]['indicators'], ...$quitado['indicators']);

    (new CatalogSeeder)->seedCatalog($catalogo);

    $tercero = $pauta->ambitos()->where('posicion', 3)->firstOrFail();
    expect($ultimo->fresh())->toBeNull()
        ->and($pauta->ambitos()->pluck('posicion')->all())->toBe([1, 2, 3])
        ->and(Indicador::count())->toBe(60)
        ->and(array_diff($movidos, $tercero->indicadores()->pluck('id')->all()))->toBeEmpty();
});

it('conserva un ámbito quitado que todavía tiene indicadores', function () {
    $this->seed(CatalogSeeder::class);
    $ultimo = PautaBase::where('numero', 1)->firstOrFail()->ambitos()->get()->last();
    $catalogo = catalogoJson();
    array_pop($catalogo['templates'][0]['sections']);

    (new CatalogSeeder)->seedCatalog($catalogo);

    expect($ultimo->fresh())->not->toBeNull()
        ->and(Indicador::count())->toBe(60);
});
