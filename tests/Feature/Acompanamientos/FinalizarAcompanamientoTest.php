<?php

use App\Enums\EstadoAcompanamiento;
use App\Exceptions\AcompanamientoYaFinalizado;
use App\Mail\AcompanamientoFinalizado;
use App\Models\AsignacionAcompanamiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDeAcompanamientos($this);
    Mail::fake();
    $this->acompanamiento = iniciarAcompanamiento($this);
});

it('rechaza finalizar con indicadores sin nivel y no cambia nada ni envía correo', function () {
    guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1001], 'logrado');

    try {
        finalizarAcompanamiento($this->observador, $this->acompanamiento);
        $this->fail('Se esperaba un error de validación en [respuestas].');
    } catch (ValidationException $excepcion) {
        expect($excepcion->errors()['respuestas'][0])->toContain('1002');
    }

    expect($this->acompanamiento->fresh()->estado)->toBe(EstadoAcompanamiento::EnCurso);
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('finaliza con todos los indicadores respondidos y encola un solo aviso al docente', function () {
    responderTodo($this, $this->acompanamiento);

    $finalizado = finalizarAcompanamiento($this->observador, $this->acompanamiento);

    expect($finalizado->estado)->toBe(EstadoAcompanamiento::Finalizado)
        ->and($this->acompanamiento->fresh()->finalizado_en)->not->toBeNull();
    Mail::assertQueuedCount(1);
    Mail::assertQueued(AcompanamientoFinalizado::class, fn (AcompanamientoFinalizado $correo): bool => $correo->hasTo($this->usuarioDocente->correo)
        && $correo->enlace === route('acompanamientos.show', $this->acompanamiento->id));
    Mail::assertNothingSent();
});

it('no envía otro correo al intentar finalizar de nuevo', function () {
    responderTodo($this, $this->acompanamiento);
    finalizarAcompanamiento($this->observador, $this->acompanamiento);

    expect(fn () => finalizarAcompanamiento($this->observador, $this->acompanamiento))->toThrow(AcompanamientoYaFinalizado::class);
    Mail::assertQueuedCount(1);
});

it('niega finalizar a otro observador', function () {
    responderTodo($this, $this->acompanamiento);
    activar($this->otroObservador, $this->a);

    expect(fn () => finalizarAcompanamiento($this->otroObservador, $this->acompanamiento))->toThrow(AuthorizationException::class);
    expect($this->acompanamiento->fresh()->estado)->toBe(EstadoAcompanamiento::EnCurso);
    Mail::assertNothingQueued();
});

it('finaliza sin correo cuando el docente no tiene cuenta', function () {
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->observadorId, 'docente_id' => $this->docenteSinAsignarId]);
    $acompanamiento = iniciarAcompanamiento($this, docenteId: $this->docenteSinAsignarId);
    responderTodo($this, $acompanamiento);

    expect(finalizarAcompanamiento($this->observador, $acompanamiento)->estado)->toBe(EstadoAcompanamiento::Finalizado);
    Mail::assertNothingQueued();
});

it('el aviso nombra al observador, la fecha y el enlace, sin resultados', function () {
    $correo = new AcompanamientoFinalizado('Ana Perez', '03-11-2026', 'https://ejemplo.test/acompanamientos/1');

    $correo->assertHasSubject('Nuevo acompañamiento de aula finalizado')
        ->assertSeeInText('Ana Perez')
        ->assertSeeInText('03-11-2026')
        ->assertSeeInText('https://ejemplo.test/acompanamientos/1')
        ->assertDontSeeInText('Logrado');
});

it('enlaza el aviso a una ruta que existe', function () {
    expect(route(AcompanamientoFinalizado::RUTA, 1))->toEndWith('/acompanamientos/1');
});
