<?php

/*
 * A2.1: the session establecimiento is never the source of truth, and a module row can never be
 * moved to another establecimiento. Exercises the host-owned EstablecimientoActual and
 * PerteneceAEstablecimiento without modifying them.
 */

use App\Exceptions\EstablecimientoAjeno;
use App\Exceptions\SinEstablecimientoActivo;
use App\Models\Pauta;
use App\Support\EstablecimientoActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->establecimiento = newEstablecimiento('1000', 'Colegio A');
    $this->ajeno = newEstablecimiento('2000', 'Colegio B');
    $this->usuario = usuarioConMembresia($this->establecimiento);
});

it('rejects moving a module row to another establecimiento on update', function () {
    activar($this->usuario, $this->establecimiento);
    $pauta = Pauta::factory()->create();

    $pauta->establecimiento_id = $this->ajeno;

    expect(fn () => $pauta->save())->toThrow(EstablecimientoAjeno::class)
        ->and(DB::table('pautas')->where('id', $pauta->id)->value('establecimiento_id'))->toBe($this->establecimiento);
});

it('resolves a tampered session establecimiento to null', function (mixed $valorManipulado) {
    $this->actingAs($this->usuario);
    session()->put(EstablecimientoActual::CLAVE_SESION, $valorManipulado);

    $actual = app(EstablecimientoActual::class);

    expect($actual->refrescar())->toBeNull()
        ->and($actual->id())->toBeNull()
        ->and(fn () => Pauta::query()->get())->toThrow(SinEstablecimientoActivo::class);
})->with([
    'non-numeric' => 'abc',
    'numeric prefix' => '1000abc',
    'negative' => '-1',
    'zero' => 0,
    'too long' => str_repeat('9', 19),
    'float' => 1.5,
    'array' => [[1]],
]);

it('resolves a foreign session establecimiento to null', function () {
    $this->actingAs($this->usuario);
    session()->put(EstablecimientoActual::CLAVE_SESION, $this->ajeno);

    $actual = app(EstablecimientoActual::class);

    expect($actual->refrescar())->toBeNull()
        ->and($actual->id())->toBeNull()
        ->and($actual->seleccionar($this->ajeno))->toBeFalse();
});

it('resolves to null once the membresia is suspended after selection', function () {
    activar($this->usuario, $this->establecimiento);
    DB::table('membresias')->where('usuario_id', $this->usuario->id)->update(['estado' => 'suspendida']);

    $actual = app(EstablecimientoActual::class);

    expect($actual->refrescar())->toBeNull()
        ->and($actual->id())->toBeNull()
        ->and(fn () => Pauta::query()->get())->toThrow(SinEstablecimientoActivo::class);
});

it('resolves to null on the next request once the membresia is suspended', function () {
    activar($this->usuario, $this->establecimiento);
    DB::table('membresias')->where('usuario_id', $this->usuario->id)->update(['estado' => 'suspendida']);

    app()->forgetScopedInstances();

    expect(app(EstablecimientoActual::class)->id())->toBeNull();
});
