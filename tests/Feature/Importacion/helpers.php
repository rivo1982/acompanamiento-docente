<?php

use App\Models\Membresia;
use Illuminate\Support\Facades\DB;

/*
 * Helpers shared by the import screen tests (required with require_once, so they are defined once).
 */

/**
 * Import row of $administrador's establecimiento, written with DB::table so any establecimiento works regardless of
 * the active one. Returns its id.
 *
 * @param  array<string, mixed>  $cambios
 */
function importacionDePrueba(Membresia $administrador, array $cambios = []): int
{
    $errores = $cambios['errores'] ?? null;
    unset($cambios['errores']);

    return DB::table('importaciones_usuarios')->insertGetId([
        'establecimiento_id' => $administrador->establecimiento_id,
        'membresia_id' => $administrador->id,
        'archivo' => 'funcionarios.xlsx',
        'estado' => 'pendiente',
        'total' => 0,
        'creados' => 0,
        'con_error' => 0,
        'errores' => $errores === null ? null : json_encode($errores),
        'created_at' => now(),
        'updated_at' => now(),
        ...$cambios,
    ]);
}

/**
 * Asserts that every text appears in $html after the previous one.
 *
 * @param  list<string>  $textos
 */
function textosEnOrden(string $html, array $textos): void
{
    $desde = 0;
    foreach ($textos as $texto) {
        $posicion = strpos($html, $texto, $desde);
        expect($posicion)->not->toBeFalse("Missing in order: {$texto}");
        $desde = $posicion + strlen($texto);
    }
}
