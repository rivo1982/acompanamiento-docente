<?php

use App\Actions\DescartarAcompanamiento;
use App\Actions\FinalizarAcompanamiento;
use App\Actions\GuardarRespuesta;
use App\Actions\IniciarAcompanamiento;
use App\Models\Acompanamiento;
use App\Models\Ambito;
use App\Models\AsignacionAcompanamiento;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\RespuestaAcompanamiento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Shared setup for the observation flow tests: two establecimientos; in A an observer assigned to a
 * docente (who has an account), a second observer, an unassigned docente, a curso and an asignatura;
 * a curso of B; and a pauta of A with two indicadores in two ámbitos ($test->ind keyed by numero).
 * The observer of A is active.
 */
function escenarioDeAcompanamientos(object $test): void
{
    $test->a = newEstablecimiento('1000', 'Colegio A');
    $test->b = newEstablecimiento('2000', 'Colegio B');
    [$test->observador, $test->observadorId] = usuarioDelModulo($test->a, 'observador');
    [$test->otroObservador, $test->otroObservadorId] = usuarioDelModulo($test->a, 'observador');
    [$test->usuarioDocente, $test->docenteId] = usuarioDelModulo($test->a, 'docente');
    $test->docenteSinAsignarId = funcionarioConFuncion($test->a, 'docente');
    $test->cursoId = newCurso($test->a);
    $test->cursoAjenoId = newCurso($test->b);
    $test->asignaturaId = DB::table('asignaturas')->insertGetId(['nombre' => 'Lenguaje']);

    activar($test->observador, $test->a);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $test->observadorId, 'docente_id' => $test->docenteId]);

    $test->ind = [];
    foreach ([[1001, 'Clima de aula'], [1002, 'Gestión de la clase']] as $posicion => [$numero, $ambito]) {
        $test->ind[$numero] = Indicador::factory()
            ->for(Ambito::factory()->create(['nombre' => $ambito]))
            ->create(['numero' => $numero])->id;
    }

    $test->pauta = Pauta::factory()->create(['nombre' => 'Pauta de aula']);
    $test->pauta->indicadores()->attach([
        $test->ind[1001] => ['posicion' => 1],
        $test->ind[1002] => ['posicion' => 2],
    ]);
}

function iniciarAcompanamiento(object $test, ?Usuario $usuario = null, ?int $docenteId = null, ?int $pautaId = null, ?int $cursoId = null, ?int $asignaturaId = null): Acompanamiento
{
    return app(IniciarAcompanamiento::class)->iniciar(
        $usuario ?? $test->observador,
        $docenteId ?? $test->docenteId,
        $pautaId ?? $test->pauta->id,
        $cursoId ?? $test->cursoId,
        $asignaturaId ?? $test->asignaturaId,
    );
}

function guardarRespuesta(Usuario $usuario, Acompanamiento $acompanamiento, int $indicadorId, string $nivel, ?string $retroalimentacion = null): RespuestaAcompanamiento
{
    return app(GuardarRespuesta::class)->guardar($usuario, $acompanamiento->id, $indicadorId, $nivel, $retroalimentacion);
}

function descartarAcompanamiento(Usuario $usuario, Acompanamiento $acompanamiento): void
{
    app(DescartarAcompanamiento::class)->descartar($usuario, $acompanamiento->id);
}

function finalizarAcompanamiento(Usuario $usuario, Acompanamiento $acompanamiento): Acompanamiento
{
    return app(FinalizarAcompanamiento::class)->finalizar($usuario, $acompanamiento->id);
}

/** Answers every indicador of the scenario pauta with "logrado". */
function responderTodo(object $test, Acompanamiento $acompanamiento): void
{
    foreach ($test->ind as $indicadorId) {
        guardarRespuesta($test->observador, $acompanamiento, $indicadorId, 'logrado');
    }
}

/** Asserts $accion throws a ValidationException with an error on $campo. */
function rechazaElCampo(Closure $accion, string $campo): void
{
    try {
        $accion();
    } catch (ValidationException $excepcion) {
        expect($excepcion->errors())->toHaveKey($campo);

        return;
    }

    test()->fail("Se esperaba un error de validación en [{$campo}].");
}
