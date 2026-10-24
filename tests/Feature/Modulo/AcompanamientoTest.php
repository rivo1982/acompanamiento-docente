<?php

/*
 * Ported from tests/Feature/Models/ObservationTest.php (commit b3397fc) to the Spanish module,
 * plus the host links (funcionarios, curso, asignatura) and the establecimiento isolation.
 */

use App\Enums\EstadoAcompanamiento;
use App\Enums\NivelLogro;
use App\Exceptions\AcompanamientoYaFinalizado;
use App\Exceptions\EntradaDeSnapshotIncompleta;
use App\Exceptions\IndicadorFueraDelSnapshot;
use App\Exceptions\PautaNoDisponible;
use App\Models\Acompanamiento;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\RespuestaAcompanamiento;
use Database\Factories\AcompanamientoFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->establecimiento = newEstablecimiento('1000');
    activar(usuarioConMembresia($this->establecimiento), $this->establecimiento);
});

it('crea el acompañamiento en curso con el snapshot de la pauta', function () {
    $pauta = Pauta::factory()->create(['nombre' => 'Pauta primer ciclo']);
    $indicador = Indicador::factory()->create(['numero' => 7, 'nombre' => 'Clima de aula']);
    $pauta->indicadores()->attach($indicador, ['posicion' => 1]);

    $acompanamiento = Acompanamiento::factory()->for($pauta)->create();

    expect($acompanamiento->fresh()->estado)->toBe(EstadoAcompanamiento::EnCurso)
        ->and($acompanamiento->establecimiento_id)->toBe($this->establecimiento)
        ->and($acompanamiento->iniciado_en)->not->toBeNull()
        ->and($acompanamiento->pauta_snapshot['nombre'])->toBe('Pauta primer ciclo')
        ->and($acompanamiento->pauta_snapshot['indicadores'][0]['numero'])->toBe(7)
        ->and($acompanamiento->pauta_snapshot['indicadores'][0]['nombre'])->toBe('Clima de aula');
});

it('congela la retroalimentación aunque el catálogo cambie después de iniciar', function () {
    $pauta = Pauta::factory()->create(['nombre' => 'Original']);
    $indicador = Indicador::factory()->create([
        'retro_que_significa' => 'Significado original',
        'retro_para_logrado' => 'Para lograr original',
        'retro_para_destacado' => 'Para destacar original',
        'fuentes' => 'Fuente original',
    ]);
    $pauta->indicadores()->attach($indicador, ['posicion' => 1]);
    $acompanamiento = Acompanamiento::factory()->for($pauta)->create();
    $respuesta = RespuestaAcompanamiento::factory()->for($acompanamiento)->create([
        'indicador_id' => $indicador->id,
        'nivel' => NivelLogro::Logrado,
    ]);

    $indicador->update(['retro_que_significa' => 'Editado', 'fuentes' => 'Editado']);
    $pauta->update(['nombre' => 'Renombrada']);

    expect($respuesta->fresh()->retroalimentacionAutomatica())->toBe([
        'que_significa' => 'Significado original',
        'para_logrado' => 'Para lograr original',
        'para_destacado' => 'Para destacar original',
        'fuentes' => 'Fuente original',
        'felicitaciones' => 'Felicitaciones.',
    ])->and($acompanamiento->fresh()->pauta_snapshot['nombre'])->toBe('Original');
});

it('no felicita bajo logrado', function () {
    $pauta = Pauta::factory()->create();
    $indicador = Indicador::factory()->create();
    $pauta->indicadores()->attach($indicador, ['posicion' => 1]);
    $acompanamiento = Acompanamiento::factory()->for($pauta)->create();

    $respuesta = RespuestaAcompanamiento::factory()->for($acompanamiento)->create([
        'indicador_id' => $indicador->id,
        'nivel' => NivelLogro::EnDesarrollo,
    ]);

    expect($respuesta->retroalimentacionAutomatica()['felicitaciones'])->toBeNull();
});

it('al finalizar registra minutos completos y el estado', function () {
    $this->travelTo(now()->setTime(10, 0));
    $acompanamiento = Acompanamiento::factory()->create(['iniciado_en' => now()]);

    $this->travel(47)->minutes();
    $this->travel(35)->seconds();
    $acompanamiento->finalizar();

    $acompanamiento->refresh();
    expect($acompanamiento->estado)->toBe(EstadoAcompanamiento::Finalizado)
        ->and($acompanamiento->duracion_minutos)->toBe(47)
        ->and($acompanamiento->finalizado_en->equalTo(now()))->toBeTrue();
});

it('rechaza finalizar dos veces y conserva el registro original', function () {
    $this->travelTo(now()->setTime(10, 0));
    $acompanamiento = Acompanamiento::factory()->create(['iniciado_en' => now()]);
    $this->travel(30)->minutes();
    $acompanamiento->finalizar();
    $finalizadoEn = $acompanamiento->fresh()->finalizado_en;
    $this->travel(20)->minutes();

    expect(fn () => $acompanamiento->finalizar())->toThrow(AcompanamientoYaFinalizado::class);

    $acompanamiento->refresh();
    expect($acompanamiento->duracion_minutos)->toBe(30)
        ->and($acompanamiento->finalizado_en->equalTo($finalizadoEn))->toBeTrue();
});

it('rechaza finalizar desde una copia desactualizada y conserva el primer cierre', function () {
    $this->travelTo(now()->setTime(10, 0));
    $acompanamiento = Acompanamiento::factory()->create(['iniciado_en' => now()]);
    $primeraCopia = Acompanamiento::findOrFail($acompanamiento->id);
    $copiaVieja = Acompanamiento::findOrFail($acompanamiento->id);
    $this->travel(30)->minutes();
    $primeraCopia->finalizar();
    $this->travel(20)->minutes();

    expect(fn () => $copiaVieja->finalizar())->toThrow(AcompanamientoYaFinalizado::class);

    $guardado = $acompanamiento->fresh();
    expect($guardado->duracion_minutos)->toBe(30)
        ->and($guardado->finalizado_en->equalTo($primeraCopia->finalizado_en))->toBeTrue();
});

it('al finalizar refresca el modelo con los valores guardados', function () {
    $this->travelTo(now()->setTime(10, 0));
    $acompanamiento = Acompanamiento::factory()->create(['iniciado_en' => now()]);
    $this->travel(12)->minutes();

    $acompanamiento->finalizar();

    expect($acompanamiento->estado)->toBe(EstadoAcompanamiento::Finalizado)
        ->and($acompanamiento->duracion_minutos)->toBe(12)
        ->and($acompanamiento->isDirty())->toBeFalse();
});

it('no sobrescribe el snapshot ni el inicio entregados', function () {
    $iniciadoEn = now()->subHour()->startOfMinute();
    $snapshot = ['id' => 0, 'nombre' => 'Provista', 'indicadores' => []];

    $acompanamiento = Acompanamiento::factory()->create([
        'iniciado_en' => $iniciadoEn,
        'pauta_snapshot' => $snapshot,
    ]);

    $acompanamiento->refresh();
    expect($acompanamiento->pauta_snapshot)->toBe($snapshot)
        ->and($acompanamiento->iniciado_en->equalTo($iniciadoEn))->toBeTrue();
});

it('falla la retroalimentación automática si el indicador no está en el snapshot', function () {
    $acompanamiento = Acompanamiento::factory()->create();
    $ajeno = Indicador::factory()->create();
    $respuesta = RespuestaAcompanamiento::factory()->for($acompanamiento)->make([
        'indicador_id' => $ajeno->id,
        'nivel' => NivelLogro::Logrado,
    ]);

    expect(fn () => $respuesta->retroalimentacionAutomatica())->toThrow(IndicadorFueraDelSnapshot::class);
});

it('falla la retroalimentación automática si la entrada del snapshot está incompleta', function () {
    $indicador = Indicador::factory()->create();
    $acompanamiento = Acompanamiento::factory()->create([
        'pauta_snapshot' => [
            'id' => 0,
            'nombre' => 'Incompleta',
            'indicadores' => [['id' => $indicador->id, 'numero' => $indicador->numero, 'nombre' => $indicador->nombre]],
        ],
    ]);
    $respuesta = RespuestaAcompanamiento::factory()->for($acompanamiento)->create([
        'indicador_id' => $indicador->id,
        'nivel' => NivelLogro::Logrado,
    ]);

    expect(fn () => $respuesta->retroalimentacionAutomatica())
        ->toThrow(EntradaDeSnapshotIncompleta::class, 'falta [retro_que_significa, retro_para_logrado, fuentes]');

    try {
        $respuesta->retroalimentacionAutomatica();
    } catch (EntradaDeSnapshotIncompleta $e) {
        expect($e)->not->toBeInstanceOf(IndicadorFueraDelSnapshot::class);
    }
});

it('permite una sola respuesta por indicador en cada acompañamiento', function () {
    $respuesta = RespuestaAcompanamiento::factory()->create();

    expect(fn () => RespuestaAcompanamiento::factory()->create([
        'acompanamiento_id' => $respuesta->acompanamiento_id,
        'indicador_id' => $respuesta->indicador_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('deriva el nivel de enseñanza desde el curso', function () {
    $nivelId = DB::table('niveles_ensenanza')->insertGetId(['nombre' => 'Educación Media', 'orden' => 5]);
    $gradoId = DB::table('grados')->insertGetId(['nivel_ensenanza_id' => $nivelId, 'nombre' => '2° medio', 'orden' => 10]);
    $cursoId = newCurso($this->establecimiento, null, $gradoId);

    $acompanamiento = Acompanamiento::factory()->create(['curso_id' => $cursoId]);

    expect($acompanamiento->nivelEnsenanza())->toBe('Educación Media')
        ->and($acompanamiento->curso->grado->nombre)->toBe('2° medio');
});

it('vincula observador y docente con funcionarios del establecimiento', function () {
    $observador = newFuncionario($this->establecimiento);
    $docente = newFuncionario($this->establecimiento);

    $acompanamiento = Acompanamiento::factory()->create(['observador_id' => $observador, 'docente_id' => $docente]);

    expect($acompanamiento->observador->id)->toBe($observador)
        ->and($acompanamiento->docente->id)->toBe($docente);
});

it('rechaza en la base un docente de otro establecimiento', function () {
    $acompanamiento = Acompanamiento::factory()->create();
    $ajeno = newFuncionario(newEstablecimiento('2000', 'Colegio B'));

    rejected(fn () => DB::table('acompanamientos')->where('id', $acompanamiento->id)->update(['docente_id' => $ajeno]), '23503');
});

it('rechaza en la base un estado desconocido', function () {
    $acompanamiento = Acompanamiento::factory()->create();

    rejected(fn () => DB::table('acompanamientos')->where('id', $acompanamiento->id)->update(['estado' => 'pausado']), '23514');
});

it('no muestra acompañamientos de otro establecimiento', function () {
    $propio = Acompanamiento::factory()->create();
    $otro = newEstablecimiento('2000', 'Colegio B');
    activar(usuarioConMembresia($otro), $otro);
    $ajeno = Acompanamiento::factory()->create();

    expect(Acompanamiento::pluck('id')->all())->toBe([$ajeno->id])
        ->and(Acompanamiento::find($propio->id))->toBeNull();
});

it('ignora en la asignación masiva el estado, el cierre y el snapshot', function () {
    $acompanamiento = new Acompanamiento([
        'estado' => EstadoAcompanamiento::Finalizado->value,
        'finalizado_en' => now(),
        'duracion_minutos' => 10,
        'pauta_snapshot' => ['id' => 0, 'nombre' => 'Inyectada', 'indicadores' => []],
    ]);

    expect($acompanamiento->estado)->toBe(EstadoAcompanamiento::EnCurso)
        ->and($acompanamiento->finalizado_en)->toBeNull()
        ->and($acompanamiento->duracion_minutos)->toBeNull()
        ->and($acompanamiento->pauta_snapshot)->toBeNull();
});

it('nace en curso por defecto según el enum', function () {
    expect((new Acompanamiento)->estado)->toBe(EstadoAcompanamiento::EnCurso);
});

it('congela el snapshot aunque se intente asignar uno en masa al crear', function () {
    $pauta = Pauta::factory()->create(['nombre' => 'Real']);

    $acompanamiento = Acompanamiento::create([
        'pauta_id' => $pauta->id,
        'observador_id' => newFuncionario($this->establecimiento),
        'docente_id' => newFuncionario($this->establecimiento),
        'curso_id' => newCurso($this->establecimiento),
        'asignatura_id' => DB::table('asignaturas')->insertGetId(['nombre' => 'Lenguaje']),
        'pauta_snapshot' => ['id' => 0, 'nombre' => 'Inyectada', 'indicadores' => []],
        'estado' => EstadoAcompanamiento::Finalizado->value,
    ]);

    expect($acompanamiento->fresh()->pauta_snapshot['nombre'])->toBe('Real')
        ->and($acompanamiento->fresh()->estado)->toBe(EstadoAcompanamiento::EnCurso);
});

it('rechaza con un error de dominio una pauta que no es visible', function (Closure $pautaId) {
    expect(fn () => Acompanamiento::factory()->create(['pauta_id' => $pautaId($this)]))
        ->toThrow(PautaNoDisponible::class, 'no existe o no está disponible');

    expect(DB::table('acompanamientos')->count())->toBe(0);
})->with([
    'inexistente' => fn () => fn (): int => 999999,
    'de otro establecimiento' => fn () => function ($test): int {
        $otro = newEstablecimiento('2000', 'Colegio B');

        return DB::table('pautas')->insertGetId(['establecimiento_id' => $otro, 'nombre' => 'Ajena']);
    },
    'eliminada' => fn () => function (): int {
        $pauta = Pauta::factory()->create();
        $pauta->delete();

        return $pauta->id;
    },
]);

it('trunca el inicio a segundos para que la duración coincida con lo guardado', function () {
    $this->travelTo(now()->setTime(10, 0, 0, 900000));
    $acompanamiento = Acompanamiento::factory()->create(['iniciado_en' => now()]);

    expect($acompanamiento->iniciado_en->micro)->toBe(0);

    $this->travelTo(now()->setTime(10, 47, 0, 100000));
    $acompanamiento->finalizar();

    $guardado = $acompanamiento->fresh();
    expect($guardado->iniciado_en->format('H:i:s.u'))->toBe('10:00:00.000000')
        ->and($guardado->duracion_minutos)->toBe(47)
        ->and($guardado->duracion_minutos)->toBe((int) floor($guardado->iniciado_en->diffInMinutes($guardado->finalizado_en, true)));
});

it('crea los cursos de las fábricas en el año escolar nombrado', function () {
    $acompanamiento = Acompanamiento::factory()->create();

    $anio = DB::table('cursos')->join('anios_escolares', 'anios_escolares.id', '=', 'cursos.anio_escolar_id')
        ->where('cursos.id', $acompanamiento->curso_id)->value('anios_escolares.anio');

    expect($anio)->toBe(AcompanamientoFactory::ANIO_ESCOLAR_DE_FABRICA)
        ->and(AcompanamientoFactory::ANIO_ESCOLAR_DE_FABRICA)->toBe(2026);
});
