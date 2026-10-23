<?php

/*
 * Module counterpart of Vinkured tests/Feature/Schema/TenantIsolationGuardTest.php, limited to
 * the tables this module owns and run against the acompanamiento_test schema.
 */

use App\Enums\EstadoAcompanamiento;
use App\Enums\NivelLogro;
use App\Enums\NivelObjetivoApoyo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Module catalog tables: global, like the host `funciones`. */
const TABLAS_GLOBALES_DEL_MODULO = ['pautas_base', 'ambitos', 'indicadores', 'apoyos_pedagogicos'];

/**
 * Module tenant tables and the tenant parents each must reference with a composite FK.
 * Key: table; value: list of "<column> -> <referenced table>".
 */
const TABLAS_DE_INQUILINO_DEL_MODULO = [
    'pautas' => [],
    'indicador_pauta' => ['pauta_id -> pautas'],
    'observadores' => ['funcionario_id -> funcionarios'],
    'asignaciones_acompanamiento' => ['observador_id -> funcionarios', 'docente_id -> funcionarios'],
    'acompanamientos' => [
        'pauta_id -> pautas', 'observador_id -> funcionarios', 'docente_id -> funcionarios', 'curso_id -> cursos',
    ],
    'respuestas_acompanamiento' => ['acompanamiento_id -> acompanamientos'],
];

/** @return list<string> */
function tablasConEstablecimiento(): array
{
    return array_map(fn ($fila) => $fila->table_name, DB::select(
        "select table_name from information_schema.columns
         where table_schema = current_schema() and column_name = 'establecimiento_id' order by 1"
    ));
}

/** @return list<object{tabla: string, referenciada: string, columnas: string, referenciadas: string}> */
function clavesForaneasDelEsquema(): array
{
    return DB::select(
        "select t.relname as tabla, rt.relname as referenciada,
                (select string_agg(a.attname::text, ',' order by k.ord)
                 from unnest(c.conkey) with ordinality k(attnum, ord)
                 join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k.attnum) as columnas,
                (select string_agg(a.attname::text, ',' order by k.ord)
                 from unnest(c.confkey) with ordinality k(attnum, ord)
                 join pg_attribute a on a.attrelid = c.confrelid and a.attnum = k.attnum) as referenciadas
         from pg_constraint c
         join pg_class t on t.oid = c.conrelid
         join pg_class rt on rt.oid = c.confrelid
         join pg_namespace n on n.oid = t.relnamespace
         where c.contype = 'f' and n.nspname = current_schema()
         order by 1, 3"
    );
}

/** @return list<string> */
function valoresDelCheck(string $tabla, string $columna): array
{
    $definicion = DB::scalar(
        "select pg_get_constraintdef(c.oid) from pg_constraint c
         join pg_class t on t.oid = c.conrelid
         join pg_namespace n on n.oid = t.relnamespace
         where n.nspname = current_schema() and t.relname = ? and c.contype = 'c' and c.conname = ?",
        [$tabla, "{$tabla}_{$columna}_check"]
    );

    preg_match_all("/'([a-z_]+)'::/", (string) $definicion, $coincidencias);

    return $coincidencias[1];
}

it('da a cada tabla de inquilino establecimiento_id y UNIQUE (establecimiento_id, id)', function () {
    $tenant = tablasConEstablecimiento();
    $faltantes = [];

    foreach (array_keys(TABLAS_DE_INQUILINO_DEL_MODULO) as $tabla) {
        $unica = (int) DB::scalar(
            "select count(*) from pg_constraint c
             join pg_class t on t.oid = c.conrelid
             join pg_namespace n on n.oid = t.relnamespace
             where n.nspname = current_schema() and t.relname = ? and c.contype = 'u'
               and (select string_agg(a.attname::text, ',' order by k.ord)
                    from unnest(c.conkey) with ordinality k(attnum, ord)
                    join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k.attnum)
                   = 'establecimiento_id,id'",
            [$tabla]
        );

        if (! in_array($tabla, $tenant, true) || $unica === 0) {
            $faltantes[] = $tabla;
        }
    }

    expect($faltantes)->toBe([]);
});

it('referencia a cada padre de inquilino con una clave foránea compuesta', function () {
    $compuestas = [];
    foreach (clavesForaneasDelEsquema() as $fk) {
        $locales = explode(',', $fk->columnas);
        $remotas = explode(',', $fk->referenciadas);
        if ($locales[0] === 'establecimiento_id' && $remotas === ['establecimiento_id', 'id'] && count($locales) === 2) {
            $compuestas[$fk->tabla][] = "{$locales[1]} -> {$fk->referenciada}";
        }
    }

    foreach (TABLAS_DE_INQUILINO_DEL_MODULO as $tabla => $esperadas) {
        expect($compuestas[$tabla] ?? [])->toEqualCanonicalizing($esperadas);
    }
});

it('no deja claves foráneas simples entre tablas de inquilino del módulo', function () {
    $tenant = tablasConEstablecimiento();
    $violaciones = [];

    foreach (clavesForaneasDelEsquema() as $fk) {
        if (! array_key_exists($fk->tabla, TABLAS_DE_INQUILINO_DEL_MODULO) || ! in_array($fk->referenciada, $tenant, true)) {
            continue;
        }
        $locales = explode(',', $fk->columnas);
        $remotas = explode(',', $fk->referenciadas);
        $posicion = array_search('establecimiento_id', $locales, true);

        if ($posicion === false || ($remotas[$posicion] ?? null) !== 'establecimiento_id') {
            $violaciones[] = "{$fk->tabla}.{$fk->columnas} -> {$fk->referenciada}";
        }
    }

    expect($violaciones)->toBe([]);
});

it('mantiene el catálogo del módulo sin establecimiento_id', function () {
    expect(array_values(array_intersect(TABLAS_GLOBALES_DEL_MODULO, tablasConEstablecimiento())))->toBe([]);
});

it('repite en los CHECK de la base los valores de los enums', function () {
    expect(valoresDelCheck('acompanamientos', 'estado'))
        ->toEqualCanonicalizing(array_column(EstadoAcompanamiento::cases(), 'value'))
        ->and(valoresDelCheck('respuestas_acompanamiento', 'nivel'))
        ->toEqualCanonicalizing(array_column(NivelLogro::cases(), 'value'))
        ->and(valoresDelCheck('apoyos_pedagogicos', 'nivel_objetivo'))
        ->toEqualCanonicalizing(array_column(NivelObjetivoApoyo::cases(), 'value'));
});
