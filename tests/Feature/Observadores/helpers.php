<?php

use App\Actions\AsignarDocentesAObservador;
use App\Actions\HabilitarObservador;
use App\Actions\QuitarObservador;
use App\Models\AsignacionAcompanamiento;
use App\Models\Observador;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/*
 * Shared setup for the observer management tests: two establecimientos, the module administrator
 * of A active, plus docentes, an asistente and a second directivo (automatic observer) in A.
 */
function escenarioDeObservadores(object $test): void
{
    $test->a = newEstablecimiento('1000', 'Colegio A');
    $test->b = newEstablecimiento('2000', 'Colegio B');
    [$test->administrador, $test->administradorId] = usuarioDelModulo($test->a, 'administrador');
    [$test->usuarioDocente, $test->docenteId] = usuarioDelModulo($test->a, 'docente');
    $test->otroDocenteId = funcionarioConFuncion($test->a, 'docente');
    $test->asistenteId = funcionarioConFuncion($test->a, 'asistente');
    $test->directivoId = funcionarioConFuncion($test->a, 'directivo');
    $test->docenteAjenoId = funcionarioConFuncion($test->b, 'docente');

    activar($test->administrador, $test->a);
}

function habilitar(Usuario $administrador, int $funcionarioId): Observador
{
    return app(HabilitarObservador::class)->habilitar($administrador, $funcionarioId);
}

function quitar(Usuario $administrador, int $funcionarioId): void
{
    app(QuitarObservador::class)->quitar($administrador, $funcionarioId);
}

/**
 * @param  list<int>  $docenteIds
 * @return array{agregados: list<int>, quitados: list<int>}
 */
function sincronizarDocentes(Usuario $administrador, int $observadorId, array $docenteIds): array
{
    return app(AsignarDocentesAObservador::class)->sincronizar($administrador, $observadorId, $docenteIds);
}

/** Docente ids assigned to $observadorId, read without the tenancy filter. */
function docentesDe(int $observadorId): array
{
    return AsignacionAcompanamiento::sinEstablecimiento()->where('observador_id', $observadorId)
        ->orderBy('docente_id')->pluck('docente_id')->map(fn (mixed $id): int => (int) $id)->all();
}

function esObservadorHabilitado(int $funcionarioId): bool
{
    return DB::table('observadores')->where('funcionario_id', $funcionarioId)->exists();
}
