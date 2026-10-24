<?php

/*
 * Ported from tests/Feature/Policies/RubricPolicyTest.php (commit b3397fc): any staff of the
 * establecimiento sees and exports its pautas, only module administrators manage them.
 */

use App\Models\Pauta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->a = newEstablecimiento('1000', 'Colegio A');
    $this->b = newEstablecimiento('2000', 'Colegio B');
    $this->usuarios = collect(['administrador', 'observador', 'docente', 'asistente'])
        ->mapWithKeys(fn (string $rol): array => [$rol => usuarioDelModulo($this->a, $rol)[0]]);
    $this->sinFuncion = usuarioConMembresia($this->a);

    activar($this->usuarios['administrador'], $this->a);
    $this->pauta = Pauta::factory()->create();
});

it('deja ver y exportar las pautas a todo funcionario del establecimiento', function (string $rol) {
    $usuario = $this->usuarios[$rol];

    expect(puede($usuario, $this->a, 'verCualquiera', Pauta::class))->toBeTrue()
        ->and(puede($usuario, $this->a, 'ver', $this->pauta))->toBeTrue()
        ->and(puede($usuario, $this->a, 'exportar', $this->pauta))->toBeTrue();
})->with(['administrador', 'observador', 'docente', 'asistente']);

it('no deja ver pautas a un miembro sin ficha de funcionario', function () {
    expect(puede($this->sinFuncion, $this->a, 'verCualquiera', Pauta::class))->toBeFalse()
        ->and(puede($this->sinFuncion, $this->a, 'ver', $this->pauta))->toBeFalse()
        ->and(puede($this->sinFuncion, $this->a, 'exportar', $this->pauta))->toBeFalse();
});

it('deja crear, actualizar y eliminar pautas solo al administrador del módulo', function (string $rol, bool $permitido) {
    $usuario = $this->usuarios[$rol];

    expect(puede($usuario, $this->a, 'crear', Pauta::class))->toBe($permitido)
        ->and(puede($usuario, $this->a, 'actualizar', $this->pauta))->toBe($permitido)
        ->and(puede($usuario, $this->a, 'eliminar', $this->pauta))->toBe($permitido);
})->with([
    'administrador' => ['administrador', true],
    'observador' => ['observador', false],
    'docente' => ['docente', false],
    'asistente' => ['asistente', false],
]);

it('niega las pautas de otro establecimiento, también a su propio administrador desde otro colegio', function () {
    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    $pautaA = Pauta::sinEstablecimiento()->findOrFail($this->pauta->id);

    expect(puede($administradorB, $this->b, 'ver', $pautaA))->toBeFalse()
        ->and(puede($administradorB, $this->b, 'exportar', $pautaA))->toBeFalse()
        ->and(puede($administradorB, $this->b, 'actualizar', $pautaA))->toBeFalse()
        ->and(puede($administradorB, $this->b, 'eliminar', $pautaA))->toBeFalse();

    $administradorA = $this->usuarios['administrador'];
    usuarioDelModulo($this->b, 'asistente', $administradorA);

    expect(puede($administradorA, $this->b, 'crear', Pauta::class))->toBeFalse()
        ->and(puede($administradorA, $this->b, 'actualizar', $pautaA))->toBeFalse()
        ->and(puede($administradorA, $this->a, 'actualizar', $pautaA))->toBeTrue();
});
