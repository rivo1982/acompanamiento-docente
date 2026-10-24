<?php

/*
 * Ported from tests/Feature/Policies/ObservationPolicyTest.php (commit b3397fc): an observer starts
 * acompanamientos only for an assigned docente and edits them only while en curso; the docente sees
 * them only once finalizado; module administrators see every acompanamiento of their establecimiento.
 */

use App\Models\Acompanamiento;
use App\Models\AsignacionAcompanamiento;
use App\Models\Funcionario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->a = newEstablecimiento('1000', 'Colegio A');
    $this->b = newEstablecimiento('2000', 'Colegio B');
    [$this->administrador] = usuarioDelModulo($this->a, 'administrador');
    [$this->observador, $this->observadorId] = usuarioDelModulo($this->a, 'observador');
    [$this->otroObservador] = usuarioDelModulo($this->a, 'observador');
    [$this->docente, $this->docenteId] = usuarioDelModulo($this->a, 'docente');
    [$this->asistente] = usuarioDelModulo($this->a, 'asistente');

    activar($this->administrador, $this->a);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->observadorId, 'docente_id' => $this->docenteId]);
    $par = ['observador_id' => $this->observadorId, 'docente_id' => $this->docenteId];
    $this->enCurso = Acompanamiento::factory()->create($par);
    $this->finalizado = Acompanamiento::factory()->finalizado()->create($par);
    $this->funcionarioDocente = Funcionario::query()->findOrFail($this->docenteId);
});

it('deja crear un acompañamiento solo al observador asignado a ese docente', function () {
    $habilidad = fn ($usuario): bool => puede($usuario, $this->a, 'crear', [Acompanamiento::class, $this->funcionarioDocente]);

    expect($habilidad($this->observador))->toBeTrue()
        ->and($habilidad($this->otroObservador))->toBeFalse()
        ->and($habilidad($this->administrador))->toBeFalse()
        ->and($habilidad($this->docente))->toBeFalse()
        ->and($habilidad($this->asistente))->toBeFalse();
});

it('niega crear al observador asignado que dejó de ser observador', function () {
    DB::table('observadores')->where('funcionario_id', $this->observadorId)->delete();

    expect(puede($this->observador, $this->a, 'crear', [Acompanamiento::class, $this->funcionarioDocente]))->toBeFalse();
});

it('deja terminar el acompañamiento en curso al observador que perdió la habilitación, pero no crear otro', function () {
    DB::table('observadores')->where('funcionario_id', $this->observadorId)->delete();

    expect(puede($this->observador, $this->a, 'actualizar', $this->enCurso))->toBeTrue()
        ->and(puede($this->observador, $this->a, 'responder', $this->enCurso))->toBeTrue()
        ->and(puede($this->observador, $this->a, 'finalizar', $this->enCurso))->toBeTrue()
        ->and(puede($this->observador, $this->a, 'crear', [Acompanamiento::class, $this->funcionarioDocente]))->toBeFalse();
});

it('niega crear al observador asignado cuando el docente ya no es funcionario vigente', function () {
    DB::table('funcionarios')->where('id', $this->docenteId)->update(['fecha_termino' => now()->subDay()->toDateString()]);

    expect(puede($this->observador, $this->a, 'crear', [Acompanamiento::class, $this->funcionarioDocente]))->toBeFalse();
});

it('deja actualizar, responder y finalizar solo a su observador mientras sigue en curso', function (string $habilidad) {
    expect(puede($this->observador, $this->a, $habilidad, $this->enCurso))->toBeTrue()
        ->and(puede($this->observador, $this->a, $habilidad, $this->finalizado))->toBeFalse()
        ->and(puede($this->otroObservador, $this->a, $habilidad, $this->enCurso))->toBeFalse()
        ->and(puede($this->administrador, $this->a, $habilidad, $this->enCurso))->toBeFalse()
        ->and(puede($this->docente, $this->a, $habilidad, $this->enCurso))->toBeFalse();
})->with(['actualizar', 'responder', 'finalizar']);

it('deja ver y exportar a su observador, al administrador y al docente solo finalizado', function (string $habilidad) {
    expect(puede($this->observador, $this->a, $habilidad, $this->enCurso))->toBeTrue()
        ->and(puede($this->administrador, $this->a, $habilidad, $this->enCurso))->toBeTrue()
        ->and(puede($this->docente, $this->a, $habilidad, $this->enCurso))->toBeFalse()
        ->and(puede($this->docente, $this->a, $habilidad, $this->finalizado))->toBeTrue()
        ->and(puede($this->otroObservador, $this->a, $habilidad, $this->finalizado))->toBeFalse()
        ->and(puede($this->asistente, $this->a, $habilidad, $this->finalizado))->toBeFalse();
})->with(['ver', 'exportar']);

it('niega los acompañamientos de otro establecimiento', function () {
    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    $finalizadoA = Acompanamiento::sinEstablecimiento()->findOrFail($this->finalizado->id);
    $enCursoA = Acompanamiento::sinEstablecimiento()->findOrFail($this->enCurso->id);

    expect(puede($administradorB, $this->b, 'ver', $finalizadoA))->toBeFalse()
        ->and(puede($administradorB, $this->b, 'exportar', $finalizadoA))->toBeFalse();

    usuarioDelModulo($this->b, 'docente', $this->observador);

    expect(puede($this->observador, $this->b, 'ver', $enCursoA))->toBeFalse()
        ->and(puede($this->observador, $this->b, 'actualizar', $enCursoA))->toBeFalse()
        ->and(puede($this->observador, $this->b, 'crear', [Acompanamiento::class, $this->funcionarioDocente]))->toBeFalse();
});
