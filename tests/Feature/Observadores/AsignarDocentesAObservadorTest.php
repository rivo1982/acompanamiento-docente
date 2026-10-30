<?php

use App\Exceptions\AsignacionAcompanamientoInvalida;
use App\Models\AsignacionAcompanamiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDeObservadores($this);
    $this->observadorId = funcionarioConFuncion($this->a, 'docente');
    habilitarObservador($this->a, $this->observadorId);
    $this->tercerDocenteId = funcionarioConFuncion($this->a, 'docente');
});

it('agrega los docentes nuevos y quita los no seleccionados', function () {
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->observadorId, 'docente_id' => $this->docenteId]);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->observadorId, 'docente_id' => $this->otroDocenteId]);

    $cambios = sincronizarDocentes($this->administrador, $this->observadorId, [$this->otroDocenteId, $this->tercerDocenteId]);

    expect($cambios)->toBe(['agregados' => [$this->tercerDocenteId], 'quitados' => [$this->docenteId]])
        ->and(docentesDe($this->observadorId))->toBe([$this->otroDocenteId, $this->tercerDocenteId]);
});

it('es idempotente y acepta ids repetidos o como texto', function () {
    sincronizarDocentes($this->administrador, $this->observadorId, [$this->docenteId]);
    $cambios = sincronizarDocentes($this->administrador, $this->observadorId, [$this->docenteId, (string) $this->docenteId]);

    expect($cambios)->toBe(['agregados' => [], 'quitados' => []])
        ->and(docentesDe($this->observadorId))->toBe([$this->docenteId]);
});

it('con una lista vacía quita todas las asignaciones del observador', function () {
    sincronizarDocentes($this->administrador, $this->observadorId, [$this->docenteId, $this->otroDocenteId]);
    sincronizarDocentes($this->administrador, $this->observadorId, []);

    expect(docentesDe($this->observadorId))->toBe([]);
});

it('asigna docentes a un observador automático', function () {
    sincronizarDocentes($this->administrador, $this->directivoId, [$this->docenteId]);

    expect(docentesDe($this->directivoId))->toBe([$this->docenteId]);
});

it('rechaza un par no elegible sin cambiar nada', function (string $caso) {
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->observadorId, 'docente_id' => $this->docenteId]);
    $docente = match ($caso) {
        'asistente' => $this->asistenteId,
        'a sí mismo' => $this->observadorId,
        'docente de otro establecimiento' => $this->docenteAjenoId,
    };

    expect(fn () => sincronizarDocentes($this->administrador, $this->observadorId, [$this->otroDocenteId, $docente]))
        ->toThrow(AsignacionAcompanamientoInvalida::class)
        ->and(docentesDe($this->observadorId))->toBe([$this->docenteId]);
})->with(['asistente', 'a sí mismo', 'docente de otro establecimiento']);

it('rechaza un observador que no puede observar', function (string $caso) {
    $observador = $caso === 'docente sin habilitar' ? $this->otroDocenteId : $this->docenteAjenoId;

    expect(fn () => sincronizarDocentes($this->administrador, $observador, [$this->tercerDocenteId]))
        ->toThrow(AsignacionAcompanamientoInvalida::class)
        ->and(docentesDe($observador))->toBe([]);
})->with(['docente sin habilitar', 'observador de otro establecimiento']);

it('niega sincronizar a quien no es administrador del módulo', function () {
    [$observador, $observadorPropioId] = usuarioDelModulo($this->a, 'observador');
    activar($observador, $this->a);

    expect(fn () => sincronizarDocentes($observador, $observadorPropioId, [$this->docenteId]))
        ->toThrow(AuthorizationException::class)
        ->and(docentesDe($observadorPropioId))->toBe([]);
});
