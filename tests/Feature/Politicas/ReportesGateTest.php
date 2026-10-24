<?php

/*
 * Ported from tests/Feature/Policies/ReportGateTest.php (commit b3397fc). Decision (user): an
 * ex-observer loses access to the docente report (6.4). Sostenedor-level access is not modeled.
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
    [$this->observadorEnCurso, $this->observadorEnCursoId] = usuarioDelModulo($this->a, 'observador');
    [$this->docente, $this->docenteId] = usuarioDelModulo($this->a, 'docente');
    [$this->otroDocente] = usuarioDelModulo($this->a, 'docente');

    activar($this->administrador, $this->a);
    foreach ([$this->observadorId, $this->observadorEnCursoId] as $observadorId) {
        AsignacionAcompanamiento::factory()->create(['observador_id' => $observadorId, 'docente_id' => $this->docenteId]);
    }
    Acompanamiento::factory()->finalizado()->create(['observador_id' => $this->observadorId, 'docente_id' => $this->docenteId]);
    Acompanamiento::factory()->create(['observador_id' => $this->observadorEnCursoId, 'docente_id' => $this->docenteId]);
    $this->funcionarioDocente = Funcionario::query()->findOrFail($this->docenteId);
});

it('deja ver los reportes del establecimiento solo al administrador del módulo', function () {
    expect(puede($this->administrador, $this->a, 'ver-reportes-establecimiento'))->toBeTrue()
        ->and(puede($this->observador, $this->a, 'ver-reportes-establecimiento'))->toBeFalse()
        ->and(puede($this->docente, $this->a, 'ver-reportes-establecimiento'))->toBeFalse();
});

it('no lleva el acceso a reportes del establecimiento a otro colegio', function () {
    usuarioDelModulo($this->b, 'docente', $this->administrador);

    expect(puede($this->administrador, $this->b, 'ver-reportes-establecimiento'))->toBeFalse();
});

it('deja ver el reporte del docente al propio docente, al administrador y a quien lo observó', function () {
    expect(puede($this->docente, $this->a, 'ver-reporte-docente', $this->funcionarioDocente))->toBeTrue()
        ->and(puede($this->administrador, $this->a, 'ver-reporte-docente', $this->funcionarioDocente))->toBeTrue()
        ->and(puede($this->observador, $this->a, 'ver-reporte-docente', $this->funcionarioDocente))->toBeTrue();
});

it('niega el reporte del docente a otro docente y al observador sin acompañamientos finalizados', function () {
    expect(puede($this->otroDocente, $this->a, 'ver-reporte-docente', $this->funcionarioDocente))->toBeFalse()
        ->and(puede($this->observadorEnCurso, $this->a, 'ver-reporte-docente', $this->funcionarioDocente))->toBeFalse();
});

it('quita el reporte del docente al ex observador', function () {
    DB::table('observadores')->where('funcionario_id', $this->observadorId)->delete();

    expect(puede($this->observador, $this->a, 'ver-reporte-docente', $this->funcionarioDocente))->toBeFalse();
});

it('niega el reporte de un docente de otro establecimiento', function () {
    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    $docenteA = Funcionario::sinEstablecimiento()->findOrFail($this->docenteId);

    expect(puede($administradorB, $this->b, 'ver-reporte-docente', $docenteA))->toBeFalse();

    usuarioDelModulo($this->b, 'observador', $this->observador);

    expect(puede($this->observador, $this->b, 'ver-reporte-docente', $docenteA))->toBeFalse();
});
