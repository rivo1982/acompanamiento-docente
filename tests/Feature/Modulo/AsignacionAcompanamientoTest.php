<?php

/*
 * Ported from tests/Feature/Models/CoachingAssignmentTest.php (commit b3397fc). A3 (R1-001):
 * the observador must be an observer (directivo or flagged in observadores) and the docente must
 * qualify as docente, both vigentes in the active establecimiento, never the same funcionario.
 */

use App\Exceptions\AsignacionAcompanamientoInvalida;
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
    $observador = funcionarioConFuncion($this->establecimiento, 'directivo');
    $primerDocente = funcionarioConFuncion($this->establecimiento, 'docente');
    $segundoDocente = funcionarioConFuncion($this->establecimiento, 'docente');

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

it('acepta como observador a un funcionario habilitado y a un directivo', function () {
    $habilitado = funcionarioConFuncion($this->establecimiento, 'docente');
    habilitarObservador($this->establecimiento, $habilitado);
    $directivo = funcionarioConFuncion($this->establecimiento, 'directivo');
    $docente = funcionarioConFuncion($this->establecimiento, 'docente');

    AsignacionAcompanamiento::factory()->create(['observador_id' => $habilitado, 'docente_id' => $docente]);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $directivo, 'docente_id' => $docente]);

    expect(AsignacionAcompanamiento::count())->toBe(2);
});

it('crea con la fábrica por defecto una asignación elegible', function () {
    expect(AsignacionAcompanamiento::factory()->create()->exists)->toBeTrue();
});

it('rechaza un observador que no es observador', function () {
    expect(fn () => AsignacionAcompanamiento::factory()->create([
        'observador_id' => funcionarioConFuncion($this->establecimiento, 'docente'),
        'docente_id' => funcionarioConFuncion($this->establecimiento, 'docente'),
    ]))->toThrow(AsignacionAcompanamientoInvalida::class, 'no es observador');

    expect(AsignacionAcompanamiento::count())->toBe(0);
});

it('rechaza un observador habilitado cuya fecha de término ya venció', function () {
    $vencido = funcionarioConFuncion($this->establecimiento, 'docente');
    habilitarObservador($this->establecimiento, $vencido);
    DB::table('funcionarios')->where('id', $vencido)->update(['fecha_termino' => now()->subDay()->toDateString()]);

    expect(fn () => AsignacionAcompanamiento::factory()->create([
        'observador_id' => $vencido,
        'docente_id' => funcionarioConFuncion($this->establecimiento, 'docente'),
    ]))->toThrow(AsignacionAcompanamientoInvalida::class, "El funcionario [{$vencido}] no es observador vigente");

    expect(AsignacionAcompanamiento::count())->toBe(0);
});

it('informa el funcionario que falta en vez de tratarlo como el id 0', function (string $faltante) {
    $ids = [
        'observador_id' => funcionarioConFuncion($this->establecimiento, 'directivo'),
        'docente_id' => funcionarioConFuncion($this->establecimiento, 'docente'),
    ];
    unset($ids[$faltante]);

    expect(fn () => AsignacionAcompanamiento::create($ids))
        ->toThrow(AsignacionAcompanamientoInvalida::class, "Falta el {$faltante} de la asignación.");

    expect(AsignacionAcompanamiento::count())->toBe(0);
})->with(['observador_id', 'docente_id']);

it('rechaza un docente que no califica como docente', function () {
    expect(fn () => AsignacionAcompanamiento::factory()->create([
        'observador_id' => funcionarioConFuncion($this->establecimiento, 'directivo'),
        'docente_id' => funcionarioConFuncion($this->establecimiento, 'asistente'),
    ]))->toThrow(AsignacionAcompanamientoInvalida::class, 'no califica como docente');
});

it('rechaza que un funcionario se asigne a sí mismo', function () {
    $funcionario = funcionarioConFuncion($this->establecimiento, 'docente');
    habilitarObservador($this->establecimiento, $funcionario);

    expect(fn () => AsignacionAcompanamiento::factory()->create(['observador_id' => $funcionario, 'docente_id' => $funcionario]))
        ->toThrow(AsignacionAcompanamientoInvalida::class, 'a sí mismo');
});

it('rechaza un docente de otro establecimiento antes de llegar a la base', function () {
    expect(fn () => AsignacionAcompanamiento::factory()->create([
        'observador_id' => funcionarioConFuncion($this->establecimiento, 'directivo'),
        'docente_id' => funcionarioConFuncion(newEstablecimiento('2000', 'Colegio B'), 'docente'),
    ]))->toThrow(AsignacionAcompanamientoInvalida::class);
});

it('revalida al cambiar el observador de una asignación', function () {
    $asignacion = AsignacionAcompanamiento::factory()->create();

    expect(fn () => $asignacion->update(['observador_id' => funcionarioConFuncion($this->establecimiento, 'asistente')]))
        ->toThrow(AsignacionAcompanamientoInvalida::class);
});
