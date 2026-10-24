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
 * Vinkured and this module both run with config('app.timezone') = America/Santiago (config/app.php). The ported
 * tests pin the zone explicitly and restore the module default afterwards, so no test leaves a foreign zone behind.
 */
function usarZonaDeVinkured(): void
{
    config(['app.timezone' => 'America/Santiago']);
    date_default_timezone_set('America/Santiago');
}

function restaurarZonaDelModulo(): void
{
    $zona = (string) env('APP_TIMEZONE', 'America/Santiago');

    config(['app.timezone' => $zona]);
    date_default_timezone_set($zona);
}
