<?php

use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run on Tests\TestCase, which refuses to refresh any database
| other than the isolated acompanamiento_test schema. RefreshDatabase is not
| applied globally: each test file opts in explicitly.
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Host helpers
|--------------------------------------------------------------------------
|
| Ported from Vinkured tests/Pest.php (same names and rows) to create host
| rows deterministically in acompanamiento_test. Only newUsuario differs: the
| host's UsuarioFactory is not copied, so it builds the same attributes here.
|
*/

function rejected(Closure $statement, string $sqlState): void
{
    try {
        DB::transaction($statement);
    } catch (QueryException $e) {
        expect($e->getCode())->toBe($sqlState);

        return;
    }

    throw new RuntimeException('Expected the statement to be rejected, but it succeeded.');
}

function seedGeography(): int
{
    $regionId = DB::table('regiones')->insertGetId(['nombre' => 'Region', 'codigo' => 'R1']);

    return DB::table('comunas')->insertGetId([
        'nombre' => 'Comuna', 'codigo' => 'C1', 'region_id' => $regionId,
    ]);
}

function insertEstablecimiento(int $comunaId, string $rbd = '1000', string $nombre = 'Colegio'): int
{
    return DB::table('establecimientos')->insertGetId([
        'rbd' => $rbd, 'dv_rbd' => '1', 'nombre' => $nombre, 'comuna_id' => $comunaId,
    ]);
}

function newEstablecimiento(string $rbd, string $nombre = 'Colegio'): int
{
    static $comunaId = null;
    if ($comunaId === null || ! DB::table('comunas')->where('id', $comunaId)->exists()) {
        $comunaId = seedGeography();
    }

    return insertEstablecimiento($comunaId, $rbd, $nombre);
}

function nextDocumento(): string
{
    static $counter = 100000;

    return (string) ++$counter;
}

function newPersona(int $estId, string $numero = '1-9', string $tipo = 'rut'): int
{
    return DB::table('personas')->insertGetId([
        'establecimiento_id' => $estId, 'tipo_documento' => $tipo, 'numero_documento' => $numero,
        'nombres' => 'Ana', 'primer_apellido' => 'Perez',
    ]);
}

function newFuncionario(int $estId): int
{
    return DB::table('funcionarios')->insertGetId([
        'establecimiento_id' => $estId,
        'persona_id' => newPersona($estId, nextDocumento()),
    ]);
}

function newAnio(int $estId, int $anio = 2026): int
{
    return DB::table('anios_escolares')->insertGetId(['establecimiento_id' => $estId, 'anio' => $anio]);
}

function newGrado(string $nombre = '1'): int
{
    $nivelId = DB::table('niveles_ensenanza')->insertGetId(['nombre' => 'Basica', 'orden' => 1]);

    return DB::table('grados')->insertGetId(['nivel_ensenanza_id' => $nivelId, 'nombre' => $nombre, 'orden' => 1]);
}

function newCurso(int $estId, ?int $anioId = null, ?int $gradoId = null, string $letra = 'A'): int
{
    return DB::table('cursos')->insertGetId([
        'establecimiento_id' => $estId,
        // Reutiliza el año 2026 del establecimiento si ya existe (es único por establecimiento y año).
        'anio_escolar_id' => $anioId
            ?? DB::table('anios_escolares')->where(['establecimiento_id' => $estId, 'anio' => 2026])->value('id')
            ?? newAnio($estId),
        'grado_id' => $gradoId ?? newGrado(),
        'letra' => $letra,
    ]);
}

/** Same attributes as Vinkured's UsuarioFactory definition, with a deterministic correo. */
function newUsuario(array $overrides = []): Usuario
{
    static $password = null;

    $usuario = new Usuario;
    $usuario->forceFill([
        'correo' => 'usuario'.nextDocumento().'@example.test',
        'email_verified_at' => now(),
        'password' => $password ??= Hash::make('password'),
        'es_soporte_vinkured' => false,
        'remember_token' => 'token'.nextDocumento(),
        ...$overrides,
    ])->save();

    return $usuario;
}

function newMembresia(int $estId, int $usuarioId, ?int $personaId = null, array $extra = []): int
{
    return DB::table('membresias')->insertGetId([
        'usuario_id' => $usuarioId,
        'establecimiento_id' => $estId,
        'persona_id' => $personaId ?? newPersona($estId, nextDocumento()),
        ...$extra,
    ]);
}

/** Usuario con una membresia (por defecto activa) en el establecimiento dado. Contrasena de prueba: "password". */
function usuarioConMembresia(int $estId, string $estado = 'activa', array $usuario = []): Usuario
{
    $u = newUsuario($usuario);
    newMembresia($estId, $u->id, null, ['estado' => $estado]);

    return $u;
}

/** Global function (catalog row). Without a key unless one is given. */
function newFuncion(string $categoria = 'docente', ?string $clave = null, ?string $nombre = null): int
{
    return DB::table('funciones')->insertGetId([
        'nombre' => $nombre ?? 'Funcion '.nextDocumento(), 'categoria' => $categoria, 'clave' => $clave,
    ]);
}

function asignarFuncion(int $estId, int $funcionarioId, int $funcionId): int
{
    return DB::table('funcionario_funciones')->insertGetId([
        'establecimiento_id' => $estId, 'funcionario_id' => $funcionarioId, 'funcion_id' => $funcionId,
    ]);
}

function newAsignacion(int $estId, int $funcionarioId, int $cursoId, bool $jefatura = true): int
{
    return DB::table('asignaciones_docentes')->insertGetId([
        'establecimiento_id' => $estId, 'funcionario_id' => $funcionarioId, 'curso_id' => $cursoId,
        'asignatura_id' => $jefatura ? null : DB::table('asignaturas')->insertGetId(['nombre' => 'Asig '.nextDocumento()]),
        'es_jefatura' => $jefatura,
    ]);
}

/**
 * Makes $usuario's membership in $estId the active one for this test, bypassing the HTTP layer.
 * Resets the per-request memory first so it can be called again to switch establecimiento.
 */
function activar(Usuario $usuario, int $estId): void
{
    test()->actingAs($usuario);
    app(EstablecimientoActual::class)->olvidar();

    expect(app(EstablecimientoActual::class)->seleccionar($estId))->toBeTrue();
}

/** Authenticated user with no active establecimiento selected. */
function sinContexto(?Usuario $usuario = null): void
{
    app(EstablecimientoActual::class)->olvidar();
    $usuario === null ? Auth::logout() : test()->actingAs($usuario);
}

/**
 * Active user of $estId with the given roles: es_administrador, optionally a funcionario linked to
 * the given funcion ids (and an optional jefatura assignment). Returns [usuario, funcionarioId|null].
 *
 * @param  list<int>  $funciones
 * @return array{0: Usuario, 1: int|null}
 */
function usuarioConRoles(int $estId, bool $administrador = false, array $funciones = [], bool $jefatura = false, ?Usuario $usuario = null, ?string $fechaTermino = null, ?string $fechaIngreso = null): array
{
    $usuario ??= newUsuario();
    $personaId = newPersona($estId, nextDocumento());
    newMembresia($estId, $usuario->id, $personaId, ['estado' => 'activa', 'es_administrador' => $administrador]);

    $funcionarioId = null;
    if ($funciones !== [] || $jefatura) {
        $funcionarioId = DB::table('funcionarios')->insertGetId([
            'establecimiento_id' => $estId, 'persona_id' => $personaId, 'fecha_termino' => $fechaTermino, 'fecha_ingreso' => $fechaIngreso,
        ]);
        foreach ($funciones as $funcionId) {
            asignarFuncion($estId, $funcionarioId, $funcionId);
        }
        if ($jefatura) {
            newAsignacion($estId, $funcionarioId, newCurso($estId, null, newGrado(), 'J'));
        }
    }

    return [$usuario, $funcionarioId];
}

/** Two establecimientos with an active Coordinador-less user in the first. Returns [$a, $b]. */
function dosEstablecimientosConActivo(): array
{
    $a = newEstablecimiento('1000', 'Colegio A');
    $b = newEstablecimiento('2000', 'Colegio B');
    activar(usuarioConMembresia($a), $a);

    return [$a, $b];
}
