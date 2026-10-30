<?php

use App\Actions\EliminarPauta;
use App\Actions\GuardarPauta;
use App\Models\Ambito;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\PautaBase;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Shared setup for the pauta management tests: two establecimientos, the module administrator of A
 * active, a docente of A, and a small catalog created out of order so ordering is observable:
 *   pauta base 2 → ámbito 1 → indicadores 2001, 2002
 *   pauta base 1 → ámbito 2 → indicador 1003; ámbito 1 → indicadores 1002, 1001
 * $test->ind is keyed by indicador numero.
 */
function escenarioDePautas(object $test): void
{
    $test->a = newEstablecimiento('1000', 'Colegio A');
    $test->b = newEstablecimiento('2000', 'Colegio B');
    [$test->administrador] = usuarioDelModulo($test->a, 'administrador');
    [$test->usuarioDocente] = usuarioDelModulo($test->a, 'docente');

    $segunda = PautaBase::factory()->create(['numero' => 9002, 'nombre' => 'Base dos']);
    $primera = PautaBase::factory()->create(['numero' => 9001, 'nombre' => 'Base uno']);
    $ambitoSegunda = Ambito::factory()->for($segunda)->create(['posicion' => 1, 'nombre' => 'Ámbito 2.1']);
    $ambitoPrimeraDos = Ambito::factory()->for($primera)->create(['posicion' => 2, 'nombre' => 'Ámbito 1.2']);
    $ambitoPrimeraUno = Ambito::factory()->for($primera)->create(['posicion' => 1, 'nombre' => 'Ámbito 1.1']);

    $test->ind = [];
    foreach ([[$ambitoSegunda, 2002], [$ambitoSegunda, 2001], [$ambitoPrimeraDos, 1003], [$ambitoPrimeraUno, 1002], [$ambitoPrimeraUno, 1001]] as [$ambito, $numero]) {
        $test->ind[$numero] = Indicador::factory()->for($ambito)->create(['numero' => $numero, 'nombre' => "Indicador {$numero}"])->id;
    }

    activar($test->administrador, $test->a);
}

/** @param  array<int|string>  $indicadorIds */
function crearPauta(Usuario $usuario, string $nombre, array $indicadorIds): Pauta
{
    return app(GuardarPauta::class)->crear($usuario, $nombre, $indicadorIds);
}

/** @param  array<int|string>  $indicadorIds */
function actualizarPauta(Usuario $usuario, int $pautaId, string $nombre, array $indicadorIds): Pauta
{
    return app(GuardarPauta::class)->actualizar($usuario, $pautaId, $nombre, $indicadorIds);
}

function eliminarPauta(Usuario $usuario, int $pautaId): void
{
    app(EliminarPauta::class)->eliminar($usuario, $pautaId);
}

/**
 * Indicador ids of the pauta in stored `posicion` order, read without the tenancy filter.
 *
 * @return list<int>
 */
function indicadoresGuardados(int $pautaId): array
{
    return DB::table('indicador_pauta')->where('pauta_id', $pautaId)->orderBy('posicion')
        ->pluck('indicador_id')->map(fn (mixed $id): int => (int) $id)->all();
}

/** @return list<int> */
function posicionesGuardadas(int $pautaId): array
{
    return DB::table('indicador_pauta')->where('pauta_id', $pautaId)->orderBy('posicion')
        ->pluck('posicion')->map(fn (mixed $posicion): int => (int) $posicion)->all();
}

/** Asserts $accion throws a ValidationException with an error on $campo; returns nothing. */
function rechazaCampo(Closure $accion, string $campo): void
{
    try {
        $accion();
    } catch (ValidationException $excepcion) {
        expect($excepcion->errors())->toHaveKey($campo);

        return;
    }

    test()->fail("Se esperaba un error de validación en [{$campo}].");
}
