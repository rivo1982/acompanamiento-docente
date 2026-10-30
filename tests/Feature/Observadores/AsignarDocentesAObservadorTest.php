<?php

use App\Exceptions\AsignacionAcompanamientoInvalida;
use App\Models\AsignacionAcompanamiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

it('revisa al observador dentro de la transacción: si lo quitan antes de guardar, no deja pares', function () {
    // Simulates a concurrent QuitarObservador that commits between the caller's view and the save.
    $quitado = false;
    Event::listen(TransactionBeginning::class, function () use (&$quitado): void {
        if (! $quitado) {
            $quitado = true;
            DB::table('observadores')->where('funcionario_id', $this->observadorId)->delete();
        }
    });

    expect(fn () => sincronizarDocentes($this->administrador, $this->observadorId, [$this->docenteId]))
        ->toThrow(AsignacionAcompanamientoInvalida::class)
        ->and($quitado)->toBeTrue()
        ->and(docentesDe($this->observadorId))->toBe([]);
});

it('bloquea la habilitación del observador antes de escribir, para serializarse con QuitarObservador', function () {
    DB::enableQueryLog();

    sincronizarDocentes($this->administrador, $this->observadorId, [$this->docenteId]);

    $consultas = array_column(DB::getQueryLog(), 'query');
    $bloqueo = collect($consultas)->search(fn (string $sql): bool => (bool) preg_match('/from "observadores".* for update$/', $sql));
    $insercion = collect($consultas)->search(fn (string $sql): bool => str_starts_with($sql, 'insert into "asignaciones_acompanamiento"'));

    expect($bloqueo)->toBeInt()
        ->and($insercion)->toBeInt()
        ->and($bloqueo)->toBeLessThan($insercion);
});
