<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return list<string> */
function tablasDelSnapshotHost(): array
{
    preg_match_all('/^CREATE TABLE public\.(\w+) \(/m', file_get_contents(database_path('schema/vinkured-host-schema.sql')), $coincidencias);

    return $coincidencias[1];
}

it('loads every host table of the snapshot into the test schema', function () {
    $hostTables = array_values(array_diff(tablasDelSnapshotHost(), ['migrations']));

    $presentes = DB::table('information_schema.tables')
        ->where('table_schema', 'acompanamiento_test')
        ->whereIn('table_name', $hostTables)
        ->pluck('table_name')
        ->all();

    expect($hostTables)->toHaveCount(53)
        ->and($presentes)->toEqualCanonicalizing($hostTables);
});

it('keeps the migrations table owned by Laravel', function () {
    expect(DB::table('migrations')->where('migration', '0000_00_00_000000_load_vinkured_host_schema')->exists())->toBeTrue();
});

it('loads the host functions and triggers into the test schema', function () {
    $funciones = DB::scalar(
        "select count(*) from pg_proc p join pg_namespace n on n.oid = p.pronamespace where n.nspname = 'acompanamiento_test'"
    );
    $triggers = DB::scalar(
        "select count(*) from pg_trigger t join pg_class c on c.oid = t.tgrelid join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'acompanamiento_test' and not t.tgisinternal"
    );

    expect($funciones)->toBe(15)->and($triggers)->toBe(19);
});

it('enforces host triggers in the test schema', function () {
    $usuario = newUsuario();

    // membresias_soporte_requiere_bandera: a membership without establecimiento needs a support usuario.
    rejected(fn () => DB::table('membresias')->insert([
        'usuario_id' => $usuario->id, 'establecimiento_id' => null, 'persona_id' => null, 'estado' => 'activa',
    ]), '23514');
});
