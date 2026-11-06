<?php

use App\Enums\EstadoAcompanamiento;
use App\Enums\NivelLogro;
use App\Livewire\Acompanamientos\Acompanamientos;
use App\Livewire\Acompanamientos\VerAcompanamiento;
use App\Livewire\Importaciones\ResultadoDeImportacion;
use App\Livewire\Invitaciones\InvitacionesPendientes;
use App\Livewire\Observadores\Observadores;
use App\Livewire\Pautas\Pautas;
use App\Mail\AcompanamientoFinalizado;
use App\Models\Usuario;
use App\Support\ApoyoPedagogico\Orientaciones;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\Demo\EscenarioDemo;
use Database\Seeders\DemoSeeder;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        'importaciones_usuarios', 'observadores', 'asignaciones_acompanamiento', 'pautas', 'indicador_pauta',
        'asignaturas', 'acompanamientos', 'respuestas_acompanamiento',
    ])->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->count()])->all();
}

function sembrarEscenarioDemo(): int
{
    test()->seed([CatalogSeeder::class, FuncionesGenericasSeeder::class]);
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

it('seeds an enabled observer and observer assignments shown on the observers screen', function () {
    $establecimientoId = sembrarEscenarioDemo();
    activar(Usuario::query()->where('correo', EscenarioDemo::CORREO_ADMINISTRADOR)->sole(), $establecimientoId);

    expect(DB::table('observadores')->where('establecimiento_id', $establecimientoId)->count())->toBe(1)
        ->and(DB::table('asignaciones_acompanamiento')->where('establecimiento_id', $establecimientoId)->count())->toBe(3);

    Livewire::test(Observadores::class)
        ->assertSee('Alicia Demo')
        ->assertSee('Beatriz Demo')
        ->assertSee('Automático');
});

it('seeds the example rubrics built from the catalog, shown on the rubrics screen', function () {
    $establecimientoId = sembrarEscenarioDemo();
    activar(Usuario::query()->where('correo', EscenarioDemo::CORREO_ADMINISTRADOR)->sole(), $establecimientoId);

    $pautas = DB::table('pautas')->where('establecimiento_id', $establecimientoId)->pluck('id', 'nombre');

    expect($pautas->keys()->sort()->values()->all())->toBe(array_keys(EscenarioDemo::PAUTAS))
        ->and(DB::table('indicador_pauta')->whereIn('pauta_id', $pautas->values())->count())
        ->toBe(array_sum(array_map('count', EscenarioDemo::PAUTAS)));

    Livewire::test(Pautas::class)->assertSee(array_keys(EscenarioDemo::PAUTAS));
});

/**
 * Demo acompañamientos of the scenario's observer, keyed by estado value.
 *
 * @return Collection<string, object>
 */
function acompanamientosDemoPorEstado(int $establecimientoId): Collection
{
    return DB::table('acompanamientos')->where('establecimiento_id', $establecimientoId)->get()->keyBy('estado');
}

it('seeds one in-progress and one finished observation of the demo teacher through the domain actions', function () {
    Mail::fake();
    $establecimientoId = sembrarEscenarioDemo();
    $acompanamientos = acompanamientosDemoPorEstado($establecimientoId);

    expect(DB::table('acompanamientos')->where('establecimiento_id', $establecimientoId)->count())->toBe(2)
        ->and($acompanamientos->keys()->sort()->values()->all())
        ->toBe([EstadoAcompanamiento::EnCurso->value, EstadoAcompanamiento::Finalizado->value])
        ->and(Auth::guest())->toBeTrue();

    $observadorId = (int) DB::table('funcionarios')->join('personas', 'personas.id', '=', 'funcionarios.persona_id')
        ->where('personas.correo', EscenarioDemo::CORREO_OBSERVADOR)->value('funcionarios.id');
    $docenteId = (int) DB::table('funcionarios')->join('personas', 'personas.id', '=', 'funcionarios.persona_id')
        ->where('personas.correo', EscenarioDemo::CORREO_DOCENTE_OBSERVADO)->value('funcionarios.id');

    foreach ($acompanamientos as $acompanamiento) {
        expect((int) $acompanamiento->observador_id)->toBe($observadorId)
            ->and((int) $acompanamiento->docente_id)->toBe($docenteId)
            ->and($acompanamiento->iniciado_en)->toBe('2026-10-14 10:00:00');
    }

    $enCurso = $acompanamientos[EstadoAcompanamiento::EnCurso->value];
    $indicadoresEnCurso = count(json_decode($enCurso->pauta_snapshot, true)['indicadores']);
    $respuestasEnCurso = DB::table('respuestas_acompanamiento')->where('acompanamiento_id', $enCurso->id)->count();

    expect($respuestasEnCurso)->toBeGreaterThan(0)->toBeLessThan($indicadoresEnCurso);

    $finalizado = $acompanamientos[EstadoAcompanamiento::Finalizado->value];
    $snapshot = json_decode($finalizado->pauta_snapshot, true);
    $respuestas = DB::table('respuestas_acompanamiento')->where('acompanamiento_id', $finalizado->id)->get();

    expect($respuestas)->toHaveCount(count($snapshot['indicadores']))
        ->and($respuestas->pluck('nivel')->unique()->sort()->values()->all())
        ->toBe(collect(NivelLogro::cases())->map->value->sort()->values()->all())
        ->and(array_intersect([42, 45], array_column($snapshot['indicadores'], 'numero')))->not->toBeEmpty()
        ->and($respuestas->whereNotNull('retroalimentacion_personal')->count())->toBeGreaterThan(0)
        ->and($finalizado->finalizado_en)->not->toBeNull();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(AcompanamientoFinalizado::class, fn (AcompanamientoFinalizado $correo): bool => $correo->hasTo(EscenarioDemo::CORREO_DOCENTE_OBSERVADO));
});

it('shows the finished observation to the observed teacher, with the protocol note', function () {
    Mail::fake();
    $establecimientoId = sembrarEscenarioDemo();
    $finalizadoId = (int) acompanamientosDemoPorEstado($establecimientoId)[EstadoAcompanamiento::Finalizado->value]->id;

    activar(Usuario::query()->where('correo', EscenarioDemo::CORREO_DOCENTE_OBSERVADO)->sole(), $establecimientoId);

    Livewire::test(Acompanamientos::class)->assertSee('Mis resultados (1)');
    Livewire::test(VerAcompanamiento::class, ['acompanamiento' => $finalizadoId])
        ->assertSee(Orientaciones::delAnexo()->notaProtocolo());
});

it('restores the example rubrics when someone deleted them in the demo, so a re-run can seed its observations', function () {
    Mail::fake();
    $establecimientoId = sembrarEscenarioDemo();
    DB::table('pautas')->where('establecimiento_id', $establecimientoId)
        ->whereIn('nombre', array_keys(EscenarioDemo::PAUTAS))->update(['deleted_at' => now()]);

    (new EscenarioDemo)->sembrar();

    expect(DB::table('pautas')->where('establecimiento_id', $establecimientoId)
        ->whereIn('nombre', array_keys(EscenarioDemo::PAUTAS))->whereNotNull('deleted_at')->count())->toBe(0)
        ->and(DB::table('pautas')->where('establecimiento_id', $establecimientoId)
            ->whereIn('nombre', array_keys(EscenarioDemo::PAUTAS))->count())->toBe(count(EscenarioDemo::PAUTAS));
});

it('is idempotent: a second run creates no duplicates and refreshes the invitation deadlines', function () {
    Mail::fake();
    sembrarEscenarioDemo();
    $antes = conteosDelEscenario();

    Carbon::setTestNow('2026-10-20 09:00:00');
    (new EscenarioDemo)->sembrar();

    expect(conteosDelEscenario())->toBe($antes)
        ->and(DB::table('membresias')->whereNotNull('invitacion_expira_en')->max('invitacion_expira_en'))
        ->toBe('2026-10-25 09:00:00');

    Mail::assertQueuedCount(1);
});
