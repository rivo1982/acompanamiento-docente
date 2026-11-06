<?php

use App\Enums\EstadoAcompanamiento;
use App\Exceptions\AcompanamientoYaFinalizado;
use App\Mail\AcompanamientoFinalizado;
use App\Models\AsignacionAcompanamiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

it('finaliza sin correo cuando el docente no tiene cuenta y lo registra en el log', function () {
    Log::spy();
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->observadorId, 'docente_id' => $this->docenteSinAsignarId]);
    $acompanamiento = iniciarAcompanamiento($this, docenteId: $this->docenteSinAsignarId);
    responderTodo($this, $acompanamiento);

    expect(finalizarAcompanamiento($this->observador, $acompanamiento)->estado)->toBe(EstadoAcompanamiento::Finalizado);
    Mail::assertNothingQueued();
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $mensaje, array $contexto): bool => $contexto === [
        'acompanamiento_id' => $acompanamiento->id,
        'docente_id' => $this->docenteSinAsignarId,
    ]);
});

it('devuelve el acompañamiento finalizado y registra el error cuando falla el aviso tras confirmar', function () {
    responderTodo($this, $this->acompanamiento);
    Log::spy();
    $falla = new RuntimeException('La cola no responde.');
    Mail::shouldReceive('to')->andThrow($falla);

    $finalizado = finalizarAcompanamiento($this->observador, $this->acompanamiento);

    expect($finalizado->estado)->toBe(EstadoAcompanamiento::Finalizado)
        ->and($this->acompanamiento->fresh()->estado)->toBe(EstadoAcompanamiento::Finalizado);
    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensaje, array $contexto): bool => $contexto['acompanamiento_id'] === $this->acompanamiento->id
        && ($contexto['exception'] ?? null) === $falla);
});

/**
 * Finishes the acompañamiento in the database right after the first (pre-lock) "finalizar" check, so the
 * locked re-read inside the transaction is the one that finds it already finished. $ademas runs then too.
 */
function finalizarloEnParalelo(object $test, ?Closure $ademas = null): void
{
    $hecho = false;
    Gate::after(function ($usuario, string $habilidad) use (&$hecho, $test, $ademas): void {
        if ($hecho || $habilidad !== 'finalizar') {
            return;
        }

        $hecho = true;
        DB::table('acompanamientos')->where('id', $test->acompanamiento->id)->update([
            'estado' => EstadoAcompanamiento::Finalizado->value, 'finalizado_en' => now(), 'duracion_minutos' => 0,
        ]);
        $ademas?->__invoke();
    });
}

it('informa al observador que ya estaba finalizado cuando otro intento lo cerró antes del bloqueo', function () {
    responderTodo($this, $this->acompanamiento);
    finalizarloEnParalelo($this);

    expect(fn () => finalizarAcompanamiento($this->observador, $this->acompanamiento))->toThrow(AcompanamientoYaFinalizado::class);
    Mail::assertNothingQueued();
});

it('autoriza antes de revelar que ya estaba finalizado dentro de la transacción', function () {
    responderTodo($this, $this->acompanamiento);
    // Meanwhile the observer stops being a funcionario in ejercicio: they can no longer see it.
    finalizarloEnParalelo($this, fn () => DB::table('funcionarios')->where('id', $this->observadorId)
        ->update(['fecha_termino' => now()->subDays(2)->toDateString()]));

    expect(fn () => finalizarAcompanamiento($this->observador, $this->acompanamiento))->toThrow(AuthorizationException::class);
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
