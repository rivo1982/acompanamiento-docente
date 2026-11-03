<?php

use App\Enums\EstadoAcompanamiento;
use App\Models\Pauta;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDeAcompanamientos($this);
});

it('inicia un acompañamiento en curso para el docente asignado, con el inicio fijado por el servidor', function () {
    $this->freezeSecond();

    $acompanamiento = iniciarAcompanamiento($this)->fresh();

    expect($acompanamiento->estado)->toBe(EstadoAcompanamiento::EnCurso)
        ->and($acompanamiento->observador_id)->toBe($this->observadorId)
        ->and($acompanamiento->docente_id)->toBe($this->docenteId)
        ->and($acompanamiento->pauta_id)->toBe($this->pauta->id)
        ->and($acompanamiento->curso_id)->toBe($this->cursoId)
        ->and($acompanamiento->asignatura_id)->toBe($this->asignaturaId)
        ->and($acompanamiento->establecimiento_id)->toBe($this->a)
        ->and($acompanamiento->iniciado_en->equalTo(now()))->toBeTrue()
        ->and($acompanamiento->nombreAsignatura())->toBe('Lenguaje');
});

it('congela en el snapshot el nombre del ámbito de cada indicador', function () {
    $snapshot = iniciarAcompanamiento($this)->pauta_snapshot;

    expect(collect($snapshot['indicadores'])->pluck('ambito', 'numero')->all())
        ->toBe([1001 => 'Clima de aula', 1002 => 'Gestión de la clase']);
});

it('niega iniciar a un observador que no tiene asignado al docente', function () {
    activar($this->otroObservador, $this->a);

    expect(fn () => iniciarAcompanamiento($this, usuario: $this->otroObservador))->toThrow(AuthorizationException::class);
    expect(fn () => iniciarAcompanamiento($this, docenteId: $this->docenteSinAsignarId))->toThrow(AuthorizationException::class);
    expect(DB::table('acompanamientos')->count())->toBe(0);
});

it('no encuentra un docente de otro establecimiento', function () {
    $docenteAjenoId = funcionarioConFuncion($this->b, 'docente');

    expect(fn () => iniciarAcompanamiento($this, docenteId: $docenteAjenoId))->toThrow(ModelNotFoundException::class);
});

it('rechaza una pauta eliminada, de otro establecimiento o sin indicadores', function () {
    $eliminada = Pauta::factory()->create();
    $eliminada->indicadores()->attach($this->ind[1001], ['posicion' => 1]);
    $eliminada->delete();
    $vacia = Pauta::factory()->create();

    rechazaElCampo(fn () => iniciarAcompanamiento($this, pautaId: $eliminada->id), 'pauta');
    rechazaElCampo(fn () => iniciarAcompanamiento($this, pautaId: $vacia->id), 'pauta');

    [$usuarioDeB] = usuarioDelModulo($this->b, 'administrador');
    activar($usuarioDeB, $this->b);
    $ajena = Pauta::factory()->create();
    activar($this->observador, $this->a);

    rechazaElCampo(fn () => iniciarAcompanamiento($this, pautaId: $ajena->id), 'pauta');
    expect(DB::table('acompanamientos')->count())->toBe(0);
});

it('rechaza un curso de otro establecimiento y una asignatura inexistente', function () {
    rechazaElCampo(fn () => iniciarAcompanamiento($this, cursoId: $this->cursoAjenoId), 'curso');
    rechazaElCampo(fn () => iniciarAcompanamiento($this, asignaturaId: $this->asignaturaId + 1000), 'asignatura');

    expect(DB::table('acompanamientos')->count())->toBe(0);
});

it('toma al observador del usuario dado y no del contexto activo', function () {
    // Even if authorization were bypassed, the observer must come from the explicit user: here the active
    // user is the assigned observer, but the given one has no assignment with the docente.
    Gate::before(fn (): bool => true);

    expect(fn () => iniciarAcompanamiento($this, usuario: $this->otroObservador))->toThrow(AuthorizationException::class);
    expect(DB::table('acompanamientos')->count())->toBe(0);
});
