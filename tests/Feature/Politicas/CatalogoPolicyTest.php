<?php

/*
 * Ported from tests/Feature/Policies/CatalogPolicyTest.php (commit b3397fc): the global catalog
 * (pautas base and indicadores) is readable by any staff of the active establecimiento.
 */

use App\Models\Indicador;
use App\Models\PautaBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->establecimiento = newEstablecimiento('1000', 'Colegio A');
    $this->pautaBase = PautaBase::factory()->create();
    $this->indicador = Indicador::factory()->create();
});

it('deja ver el catálogo a todo funcionario', function (string $rol) {
    [$usuario] = usuarioDelModulo($this->establecimiento, $rol);

    expect(puede($usuario, $this->establecimiento, 'verCualquiera', PautaBase::class))->toBeTrue()
        ->and(puede($usuario, $this->establecimiento, 'ver', $this->pautaBase))->toBeTrue()
        ->and(puede($usuario, $this->establecimiento, 'verCualquiera', Indicador::class))->toBeTrue()
        ->and(puede($usuario, $this->establecimiento, 'ver', $this->indicador))->toBeTrue();
})->with(['administrador', 'observador', 'docente', 'asistente']);

it('no deja ver el catálogo a un miembro sin ficha de funcionario', function () {
    $usuario = usuarioConMembresia($this->establecimiento);

    expect(puede($usuario, $this->establecimiento, 'verCualquiera', PautaBase::class))->toBeFalse()
        ->and(puede($usuario, $this->establecimiento, 'ver', $this->indicador))->toBeFalse();
});

it('no deja ver el catálogo a un invitado', function () {
    expect(Gate::forUser(null)->allows('verCualquiera', PautaBase::class))->toBeFalse()
        ->and(Gate::forUser(null)->allows('ver', $this->indicador))->toBeFalse();
});

it('no deja modificar el catálogo a nadie', function () {
    [$administrador] = usuarioDelModulo($this->establecimiento, 'administrador');

    expect(puede($administrador, $this->establecimiento, 'actualizar', $this->pautaBase))->toBeFalse()
        ->and(puede($administrador, $this->establecimiento, 'actualizar', $this->indicador))->toBeFalse();
});
