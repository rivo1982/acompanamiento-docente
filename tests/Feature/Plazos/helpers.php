<?php

use Illuminate\Support\Facades\DB;

/*
 * Helpers for tests ported from Vinkured tests/Feature/Plazos (required with require_once). `diaNoHabil` is a copy of
 * the helper in Vinkured tests/Pest.php, which this repository's tests/Pest.php does not define.
 */

if (! function_exists('diaNoHabil')) {
    function diaNoHabil(?int $estId, string $fecha, string $descripcion = 'Dia no habil'): int
    {
        return DB::table('dias_no_habiles')->insertGetId([
            'establecimiento_id' => $estId, 'fecha' => $fecha, 'descripcion' => $descripcion,
            'origen' => $estId === null ? 'oficial' : 'establecimiento',
        ]);
    }
}

/**
 * Vinkured runs with config('app.timezone') = America/Santiago (its config/app.php); this repository still uses UTC.
 * The ported tests assume the host zone, so they switch to it (config + PHP default) and back.
 */
function usarZonaDeVinkured(): void
{
    config(['app.timezone' => 'America/Santiago']);
    date_default_timezone_set('America/Santiago');
}

function restaurarZonaDelModulo(): void
{
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');
}
