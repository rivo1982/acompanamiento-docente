<?php

use App\Livewire\Importaciones\ResultadoDeImportacion;
use App\Livewire\Invitaciones\InvitacionesPendientes;
use App\Models\Usuario;
use Database\Seeders\Demo\EscenarioDemo;
use Database\Seeders\DemoSeeder;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

require_once __DIR__.'/../Plazos/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    usarZonaDeVinkured();
    // Wednesday 14-10-2026, 10:00 (America/Santiago).
    Carbon::setTestNow('2026-10-14 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
    restaurarZonaDelModulo();
});

/** Rows of every table the demo scenario writes, to compare two runs. */
function conteosDelEscenario(): array
{
    return collect([
        'regiones', 'comunas', 'establecimientos', 'anios_escolares', 'niveles_ensenanza', 'grados', 'cursos',
        'personas', 'funcionarios', 'funcionario_funciones', 'usuarios', 'membresias', 'asignaciones_docentes',
        'importaciones_usuarios',
    ])->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->count()])->all();
}

function sembrarEscenarioDemo(): int
{
    test()->seed(FuncionesGenericasSeeder::class);
    (new EscenarioDemo)->sembrar();

    return (int) DB::table('establecimientos')->where('rbd', EscenarioDemo::RBD)->value('id');
}

it('refuses to seed the demo when the search_path is the test schema', function () {
    (new DemoSeeder)->run();
})->throws(RuntimeException::class, 'acompanamiento_demo');

it('writes nothing when it refuses to seed', function () {
    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);

    expect(DB::table('establecimientos')->count())->toBe(0)
        ->and(DB::table('funciones')->count())->toBe(0);
});

it('refuses to seed the demo when the search_path is or includes public', function (string $searchPath) {
    config()->set('database.connections.pgsql.search_path', $searchPath);

    DemoSeeder::asegurarEsquemaDemo();
})->with(['public', 'acompanamiento_demo,public', ''])->throws(RuntimeException::class, 'acompanamiento_demo');

it('refuses to seed the demo when the default connection is not pgsql', function () {
    config()->set('database.default', 'sqlite');

    expect(fn () => DemoSeeder::asegurarEsquemaDemo())->toThrow(RuntimeException::class, 'driver "sqlite"');

    // RefreshDatabase rolls back on the default connection when the test ends.
    config()->set('database.default', 'pgsql');
});

it('refuses to seed the demo when the live session does not resolve to the demo schema', function () {
    // The configuration claims the demo schema, but the open connection still points at acompanamiento_test.
    config()->set('database.connections.pgsql.search_path', DemoSeeder::SCHEMA);

    DemoSeeder::asegurarEsquemaDemo();
})->throws(RuntimeException::class, 'resolves to schema "acompanamiento_test"');

it('seeds an administrator who can sign in with the demo password and is module administrator', function () {
    $establecimientoId = sembrarEscenarioDemo();
    $administrador = Usuario::query()->where('correo', EscenarioDemo::CORREO_ADMINISTRADOR)->sole();

    expect(Hash::check(EscenarioDemo::CONTRASENA, $administrador->password))->toBeTrue()
        ->and(DB::table('membresias')->where([
            'usuario_id' => $administrador->id, 'establecimiento_id' => $establecimientoId,
            'estado' => 'activa', 'es_administrador' => true,
        ])->exists())->toBeTrue()
        ->and(DB::table('funcionario_funciones')
            ->join('funciones', 'funciones.id', '=', 'funcionario_funciones.funcion_id')
            ->join('funcionarios', 'funcionarios.id', '=', 'funcionario_funciones.funcionario_id')
            ->join('membresias', 'membresias.persona_id', '=', 'funcionarios.persona_id')
            ->where('membresias.usuario_id', $administrador->id)
            ->value('funciones.categoria'))->toBe('directivo');

    expect(puede($administrador, $establecimientoId, 'invitar-funcionarios'))->toBeTrue();
});

it('seeds active docentes and pending invitations in every list state', function () {
    $establecimientoId = sembrarEscenarioDemo();

    expect(DB::table('membresias')->where(['establecimiento_id' => $establecimientoId, 'estado' => 'activa'])->count())
        ->toBe(1 + count(EscenarioDemo::DOCENTES))
        ->and(DB::table('membresias')->where(['establecimiento_id' => $establecimientoId, 'estado' => 'invitada'])->count())
        ->toBe(count(EscenarioDemo::INVITACIONES))
        ->and(DB::table('membresias')->where('estado', 'invitada')->whereNotNull('invitacion_hash')
            ->pluck('invitacion_hash')->every(fn (string $hash): bool => preg_match('/^[0-9a-f]{64}$/', $hash) === 1))->toBeTrue();

    activar(Usuario::query()->where('correo', EscenarioDemo::CORREO_ADMINISTRADOR)->sole(), $establecimientoId);

    Livewire::test(InvitacionesPendientes::class)
        ->assertSeeInOrder(['Vencida', 'Vence hoy', 'En plazo', 'Anulada'])
        ->assertSee('Elena Demo');
});

it('seeds a completed, a failed and a pending bulk import', function () {
    $establecimientoId = sembrarEscenarioDemo();
    activar(Usuario::query()->where('correo', EscenarioDemo::CORREO_ADMINISTRADOR)->sole(), $establecimientoId);

    $ids = DB::table('importaciones_usuarios')->where('establecimiento_id', $establecimientoId)->pluck('id', 'estado');

    expect($ids->keys()->sort()->values()->all())->toBe(['completada', 'fallida', 'pendiente']);

    Livewire::test(ResultadoDeImportacion::class, ['importacion' => $ids['completada']])
        ->assertSee('El correo no es válido.');
    Livewire::test(ResultadoDeImportacion::class, ['importacion' => $ids['fallida']])
        ->assertSee('El archivo no tiene las columnas esperadas.');
    Livewire::test(ResultadoDeImportacion::class, ['importacion' => $ids['pendiente']])
        ->assertSeeHtml('wire:poll');
});

it('is idempotent: a second run creates no duplicates and refreshes the invitation deadlines', function () {
    sembrarEscenarioDemo();
    $antes = conteosDelEscenario();

    Carbon::setTestNow('2026-10-20 09:00:00');
    (new EscenarioDemo)->sembrar();

    expect(conteosDelEscenario())->toBe($antes)
        ->and(DB::table('membresias')->whereNotNull('invitacion_expira_en')->max('invitacion_expira_en'))
        ->toBe('2026-10-25 09:00:00');
});
