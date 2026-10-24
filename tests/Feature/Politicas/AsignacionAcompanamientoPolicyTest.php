<?php

/*
 * Ported from tests/Feature/Policies/CoachingAssignmentPolicyTest.php (commit b3397fc): only the
 * module administrator manages observer → docente assignments, and only for an eligible pair.
 */

use App\Models\AsignacionAcompanamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->a = newEstablecimiento('1000', 'Colegio A');
    $this->b = newEstablecimiento('2000', 'Colegio B');
    [$this->administrador] = usuarioDelModulo($this->a, 'administrador');
    [$this->observador, $this->observadorId] = usuarioDelModulo($this->a, 'observador');
    [$this->docente, $this->docenteId] = usuarioDelModulo($this->a, 'docente');
    $this->asistenteId = funcionarioConFuncion($this->a, 'asistente');

    activar($this->administrador, $this->a);
    $this->asignacion = AsignacionAcompanamiento::factory()->create();
});

it('deja listar, crear y eliminar asignaciones solo al administrador del módulo', function () {
    $par = [AsignacionAcompanamiento::class, $this->observadorId, $this->docenteId];

    expect(puede($this->administrador, $this->a, 'verCualquiera', AsignacionAcompanamiento::class))->toBeTrue()
        ->and(puede($this->administrador, $this->a, 'crear', $par))->toBeTrue()
        ->and(puede($this->administrador, $this->a, 'eliminar', $this->asignacion))->toBeTrue();

    foreach ([$this->observador, $this->docente] as $usuario) {
        expect(puede($usuario, $this->a, 'verCualquiera', AsignacionAcompanamiento::class))->toBeFalse()
            ->and(puede($usuario, $this->a, 'crear', $par))->toBeFalse()
            ->and(puede($usuario, $this->a, 'eliminar', $this->asignacion))->toBeFalse();
    }
});

it('niega crear una asignación con un par no elegible', function (string $observador, string $docente) {
    $ids = ['observador' => $this->observadorId, 'docente' => $this->docenteId, 'asistente' => $this->asistenteId];

    expect(puede($this->administrador, $this->a, 'crear', [AsignacionAcompanamiento::class, $ids[$observador], $ids[$docente]]))->toBeFalse();
})->with([
    'observador que no observa' => ['asistente', 'docente'],
    'docente que no es docente' => ['observador', 'asistente'],
    'a sí mismo' => ['observador', 'observador'],
]);

it('niega crear con un docente de otro establecimiento', function () {
    $ajeno = funcionarioConFuncion($this->b, 'docente');

    expect(puede($this->administrador, $this->a, 'crear', [AsignacionAcompanamiento::class, $this->observadorId, $ajeno]))->toBeFalse();
});

it('niega gestionar asignaciones de otro establecimiento', function () {
    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    $asignacionA = AsignacionAcompanamiento::sinEstablecimiento()->findOrFail($this->asignacion->id);

    expect(puede($administradorB, $this->b, 'eliminar', $asignacionA))->toBeFalse()
        ->and(puede($administradorB, $this->b, 'crear', [AsignacionAcompanamiento::class, $this->observadorId, $this->docenteId]))->toBeFalse();
});
