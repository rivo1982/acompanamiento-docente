<?php

/*
 * Ported from tests/Feature/Models/CoachingAssignmentTest.php (commit b3397fc). Observer
 * eligibility (directivo/UTP by derivation, flagged funcionarios) is rebuilt in A3.
 */

use App\Models\AsignacionAcompanamiento;
use App\Models\Funcionario;
use App\Models\Observador;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->establecimiento = newEstablecimiento('1000');
    activar(usuarioConMembresia($this->establecimiento), $this->establecimiento);
});

it('vincula observador y docentes en ambos sentidos', function () {
    $observador = newFuncionario($this->establecimiento);
    $primerDocente = newFuncionario($this->establecimiento);
    $segundoDocente = newFuncionario($this->establecimiento);

    AsignacionAcompanamiento::factory()->create(['observador_id' => $observador, 'docente_id' => $primerDocente]);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $observador, 'docente_id' => $segundoDocente]);

    $asignaciones = AsignacionAcompanamiento::where('observador_id', $observador)->with('docente')->get();
    expect($asignaciones->pluck('docente.id')->all())->toEqualCanonicalizing([$primerDocente, $segundoDocente])
        ->and(AsignacionAcompanamiento::where('docente_id', $primerDocente)->first()->observador->id)->toBe($observador)
        ->and(AsignacionAcompanamiento::count())->toBe(2);
});

it('no asigna dos veces el mismo observador al mismo docente', function () {
    $asignacion = AsignacionAcompanamiento::factory()->create();

    expect(fn () => AsignacionAcompanamiento::factory()->create([
        'observador_id' => $asignacion->observador_id,
        'docente_id' => $asignacion->docente_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('rechaza en la base que un funcionario se observe a sí mismo', function () {
    $funcionario = newFuncionario($this->establecimiento);

    rejected(fn () => DB::table('asignaciones_acompanamiento')->insert([
        'establecimiento_id' => $this->establecimiento, 'observador_id' => $funcionario, 'docente_id' => $funcionario,
    ]), '23514');
});

it('rechaza en la base un docente de otro establecimiento', function () {
    $ajeno = newFuncionario(newEstablecimiento('2000', 'Colegio B'));

    rejected(fn () => DB::table('asignaciones_acompanamiento')->insert([
        'establecimiento_id' => $this->establecimiento,
        'observador_id' => newFuncionario($this->establecimiento),
        'docente_id' => $ajeno,
    ]), '23503');
});

it('habilita un funcionario como observador una sola vez', function () {
    $funcionario = newFuncionario($this->establecimiento);

    $observador = Observador::factory()->create(['funcionario_id' => $funcionario]);

    expect($observador->establecimiento_id)->toBe($this->establecimiento)
        ->and($observador->funcionario)->toBeInstanceOf(Funcionario::class)
        ->and(fn () => Observador::factory()->create(['funcionario_id' => $funcionario]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rechaza en la base un observador habilitado con un funcionario ajeno', function () {
    $ajeno = newFuncionario(newEstablecimiento('2000', 'Colegio B'));

    rejected(fn () => DB::table('observadores')->insert([
        'establecimiento_id' => $this->establecimiento, 'funcionario_id' => $ajeno,
    ]), '23503');
});
