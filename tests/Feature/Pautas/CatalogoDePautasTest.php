<?php

use App\Support\CatalogoDePautas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDePautas($this);
});

it('agrupa el catálogo por pauta base, ámbito e indicador, en orden', function () {
    $catalogo = app(CatalogoDePautas::class)->catalogo();

    expect(array_column($catalogo, 'numero'))->toBe([9001, 9002])
        ->and(array_column($catalogo[0]['ambitos'], 'nombre'))->toBe(['Ámbito 1.1', 'Ámbito 1.2'])
        ->and(array_column($catalogo[0]['ambitos'][0]['indicadores'], 'numero'))->toBe([1001, 1002])
        ->and($catalogo[0]['ambitos'][0]['indicadores'][0])->toBe(['id' => $this->ind[1001], 'numero' => 1001, 'nombre' => 'Indicador 1001'])
        ->and(array_column($catalogo[1]['ambitos'][0]['indicadores'], 'id'))->toBe([$this->ind[2001], $this->ind[2002]])
        ->and($catalogo[1])->toMatchArray(['nombre' => 'Base dos']);
});

it('lee el catálogo en un número fijo de consultas', function () {
    DB::enableQueryLog();
    app(CatalogoDePautas::class)->catalogo();

    expect(DB::getQueryLog())->toHaveCount(3);
});

it('lista las pautas vigentes del establecimiento activo con su cantidad de indicadores', function () {
    crearPauta($this->administrador, 'b segunda', [$this->ind[1001], $this->ind[1002], $this->ind[2001]]);
    crearPauta($this->administrador, 'A primera', [$this->ind[1001]]);
    $eliminada = crearPauta($this->administrador, 'Eliminada', [$this->ind[1001]]);
    eliminarPauta($this->administrador, $eliminada->id);

    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    app()->forgetScopedInstances();
    activar($administradorB, $this->b);
    crearPauta($administradorB, 'De otro colegio', [$this->ind[1001]]);
    app()->forgetScopedInstances();
    activar($this->administrador, $this->a);

    $pautas = app(CatalogoDePautas::class)->pautasDelEstablecimiento();

    expect($pautas->pluck('nombre')->all())->toBe(['A primera', 'b segunda'])
        ->and($pautas->pluck('indicadores_count')->map(fn (mixed $total): int => (int) $total)->all())->toBe([1, 3])
        ->and($pautas->first()->updated_at)->not->toBeNull();
});

it('devuelve los ids de los indicadores de una pauta en su orden', function () {
    $pauta = crearPauta($this->administrador, 'Pauta', [$this->ind[2001], $this->ind[1002]]);

    expect(app(CatalogoDePautas::class)->indicadoresDe($pauta))->toBe([$this->ind[1002], $this->ind[2001]]);
});
