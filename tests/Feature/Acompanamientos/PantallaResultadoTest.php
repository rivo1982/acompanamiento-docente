<?php

use App\Livewire\Acompanamientos\VerAcompanamiento;
use App\Mail\AcompanamientoFinalizado;
use App\Models\Acompanamiento;
use App\Models\Ambito;
use App\Models\ApoyoPedagogico;
use App\Models\AsignacionAcompanamiento;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Support\ApoyoPedagogico\Orientaciones;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

/*
 * Result screen (A3). The scenario pauta gets five indicators, scored as: 1001 En inicio (with
 * personal feedback), 1002 Destacado, 42 En desarrollo (protocol descriptor), 1003 Logrado and
 * 45 En desarrollo (protocol descriptor). All five indicators have a ficha.
 */
beforeEach(function () {
    $this->withoutVite();
    Model::preventLazyLoading();
    Mail::fake();
    escenarioDeAcompanamientos($this);

    $ambito = Ambito::factory()->create(['nombre' => 'Responsabilidades profesionales']);
    $this->ind[42] = Indicador::factory()->for($ambito)->create(['numero' => 42, 'nombre' => 'Decisiones éticas del rol'])->id;
    $this->ind[1003] = Indicador::factory()->for($ambito)->create(['numero' => 1003, 'nombre' => 'Reflexión sobre la práctica'])->id;
    $this->ind[45] = Indicador::factory()->for($ambito)->create(['numero' => 45, 'nombre' => 'Resguardo de la integridad'])->id;
    Indicador::query()->whereKey($this->ind[1001])->update(['nombre' => 'Ambiente de respeto', 'nivel_en_inicio' => 'Descriptor en inicio del ambiente.']);
    Indicador::query()->whereKey($this->ind[1002])->update(['nombre' => 'Uso del tiempo', 'nivel_destacado' => 'Descriptor destacado del tiempo.']);
    $this->pauta->indicadores()->attach([
        $this->ind[42] => ['posicion' => 3],
        $this->ind[1003] => ['posicion' => 4],
        $this->ind[45] => ['posicion' => 5],
    ]);

    foreach ([1001, 1002, 42, 1003, 45] as $numero) {
        ApoyoPedagogico::factory()->create([
            'indicador_id' => $this->ind[$numero],
            'foco_mejora' => "Foco de la ficha {$numero}.",
            'accion_metodologica' => "Metodológica de la ficha {$numero}.",
            'accion_procedimental' => "Procedimental de la ficha {$numero}.",
            'accion_formativa' => "Formativa de la ficha {$numero}.",
            'evidencia_avance' => "Evidencia de la ficha {$numero}.",
            'referencias' => "Referencias de la ficha {$numero}.",
        ]);
    }
});

/** Finishes an acompañamiento of the scenario observer with the five levels described above. */
function resultadoFinalizado(object $test): Acompanamiento
{
    $acompanamiento = iniciarAcompanamiento($test);
    guardarRespuesta($test->observador, $acompanamiento, $test->ind[1001], 'en_inicio', 'Muy buen saludo inicial al curso.');
    guardarRespuesta($test->observador, $acompanamiento, $test->ind[1002], 'destacado');
    guardarRespuesta($test->observador, $acompanamiento, $test->ind[42], 'en_desarrollo');
    guardarRespuesta($test->observador, $acompanamiento, $test->ind[1003], 'logrado');
    guardarRespuesta($test->observador, $acompanamiento, $test->ind[45], 'en_desarrollo');

    return finalizarAcompanamiento($test->observador, $acompanamiento);
}

/** Finishes the scenario acompañamiento and switches to its observed teacher, the screen's main reader. */
function resultadoComoDocente(object $test): Acompanamiento
{
    $acompanamiento = resultadoFinalizado($test);
    verComo($test, $test->usuarioDocente);

    return $acompanamiento;
}

function verComo(object $test, $usuario, ?int $establecimientoId = null): void
{
    app()->forgetScopedInstances();
    activar($usuario, $establecimientoId ?? $test->a);
}

/** Visible text of an HTML page, without tags and with whitespace collapsed to single spaces. */
function textoVisibleDelResultado(string $html): string
{
    $sinScripts = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);

    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($sinScripts))));
}

// --- Access --------------------------------------------------------------------------------------

it('muestra al docente el resultado de su acompañamiento finalizado', function () {
    $acompanamiento = resultadoComoDocente($this);

    $this->get(route('acompanamientos.show', $acompanamiento->id))
        ->assertOk()
        ->assertSeeLivewire(VerAcompanamiento::class)
        ->assertSee('<title>Retroalimentación del acompañamiento - VINKURED</title>', false)
        ->assertSee('Retroalimentación del acompañamiento')
        ->assertSee('Pauta de aula')
        ->assertSee('Lenguaje');
});

it('no muestra al docente un acompañamiento en curso', function () {
    $acompanamiento = iniciarAcompanamiento($this);
    verComo($this, $this->usuarioDocente);

    $this->get(route('acompanamientos.show', $acompanamiento->id))->assertForbidden();
});

it('no muestra a un docente el resultado de otro docente', function () {
    $acompanamiento = resultadoFinalizado($this);
    [$otroDocente] = usuarioDelModulo($this->a, 'docente');
    verComo($this, $otroDocente);

    $this->get(route('acompanamientos.show', $acompanamiento->id))->assertForbidden();
});

it('muestra el resultado a su observador y al administrador del módulo', function () {
    $acompanamiento = resultadoFinalizado($this);

    verComo($this, $this->observador);
    $this->get(route('acompanamientos.show', $acompanamiento->id))->assertOk();

    [$administrador] = usuarioDelModulo($this->a, 'administrador');
    verComo($this, $administrador);
    $this->get(route('acompanamientos.show', $acompanamiento->id))->assertOk();
});

it('no encuentra un acompañamiento de otro establecimiento ni uno inexistente', function () {
    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    verComo($this, $administradorB, $this->b);
    $ajeno = Acompanamiento::factory()->create()->id;
    verComo($this, $this->observador);

    $this->get(route('acompanamientos.show', $ajeno))->assertNotFound();
    $this->get(route('acompanamientos.show', 999999))->assertNotFound();
});

it('abre para el docente el enlace del correo de aviso', function () {
    resultadoFinalizado($this);

    $enlace = null;
    Mail::assertQueued(AcompanamientoFinalizado::class, function (AcompanamientoFinalizado $correo) use (&$enlace): bool {
        $enlace = $correo->enlace;

        return true;
    });
    verComo($this, $this->usuarioDocente);

    $this->get($enlace)->assertOk()->assertSeeLivewire(VerAcompanamiento::class);
});

// --- Content (seen by the observed teacher) ------------------------------------------------------

it('ordena las secciones: para trabajar ahora, para llevar a destacado y logros destacados', function () {
    $acompanamiento = resultadoComoDocente($this);

    $this->get(route('acompanamientos.show', $acompanamiento->id))
        ->assertOk()
        ->assertSeeInOrder([
            'Para trabajar ahora', 'Ambiente de respeto', 'Decisiones éticas del rol', 'Resguardo de la integridad',
            'Para llevar a Destacado', 'Reflexión sobre la práctica',
            'Logros destacados', 'Uso del tiempo',
        ]);
});

/*
 * Uses its own pauta with a different count per level (3, 2, 1 and 0) so a wrong count cannot pass.
 * Contract for the summary: each level label is immediately followed by its count in the visible text.
 */
it('cuenta los indicadores por nivel en el resumen', function () {
    $ambito = Ambito::factory()->create(['nombre' => 'Ámbito del resumen']);
    $pauta = Pauta::factory()->create(['nombre' => 'Pauta del resumen']);
    $niveles = ['en_inicio', 'en_inicio', 'en_inicio', 'en_desarrollo', 'en_desarrollo', 'logrado'];
    $indicadores = [];
    foreach ($niveles as $posicion => $nivel) {
        $indicadores[$posicion] = Indicador::factory()->for($ambito)->create(['numero' => 2001 + $posicion])->id;
        $pauta->indicadores()->attach([$indicadores[$posicion] => ['posicion' => $posicion + 1]]);
    }

    $acompanamiento = iniciarAcompanamiento($this, pautaId: $pauta->id);
    foreach ($niveles as $posicion => $nivel) {
        guardarRespuesta($this->observador, $acompanamiento, $indicadores[$posicion], $nivel);
    }
    $acompanamiento = finalizarAcompanamiento($this->observador, $acompanamiento);
    verComo($this, $this->usuarioDocente);

    $texto = textoVisibleDelResultado($this->get(route('acompanamientos.show', $acompanamiento->id))->assertOk()->getContent());

    expect($texto)
        ->toMatch('/En inicio\s*3(?!\d)/u')
        ->toMatch('/En desarrollo\s*2(?!\d)/u')
        ->toMatch('/Logrado\s*1(?!\d)/u')
        ->toMatch('/Destacado\s*0(?!\d)/u');
});

it('muestra la ficha de apoyo bajo destacado y no en destacado', function () {
    $acompanamiento = resultadoComoDocente($this);

    $this->get(route('acompanamientos.show', $acompanamiento->id))
        ->assertSeeInOrder([
            'Ambiente de respeto', 'Descriptor en inicio del ambiente.',
            'Foco de mejora', 'Foco de la ficha 1001.',
            'Acción metodológica', 'Metodológica de la ficha 1001.',
            'Acción procedimental', 'Procedimental de la ficha 1001.',
            'Acción formativa', 'Formativa de la ficha 1001.',
            'Evidencia de avance', 'Evidencia de la ficha 1001.',
            'Referencias y recursos', 'Referencias de la ficha 1001.',
        ])
        ->assertSee('Foco de la ficha 1003.')
        ->assertSee('Descriptor destacado del tiempo.')
        ->assertDontSee('Foco de la ficha 1002.');
});

it('no muestra bloque de ficha a un indicador sin ficha', function () {
    ApoyoPedagogico::query()->where('indicador_id', $this->ind[1001])->delete();
    $acompanamiento = resultadoComoDocente($this);

    $this->get(route('acompanamientos.show', $acompanamiento->id))
        ->assertOk()
        ->assertSee('Ambiente de respeto')
        ->assertDontSee('Foco de la ficha 1001.')
        ->assertSee('Foco de la ficha 42.');
});

it('felicita en logrado y destacado según el nivel, y muestra la retroalimentación personal', function () {
    $acompanamiento = resultadoComoDocente($this);

    $this->get(route('acompanamientos.show', $acompanamiento->id))
        ->assertSeeInOrder(['Reflexión sobre la práctica', 'Felicitaciones.', 'Uso del tiempo', 'Felicitaciones por su labor y su exitoso desarrollo profesional.'])
        ->assertSee('Muy buen saludo inicial al curso.');
});

it('muestra la nota de protocolo solo en los descriptores 42 y 45', function () {
    $acompanamiento = resultadoComoDocente($this);
    $nota = e(Orientaciones::delAnexo()->notaProtocolo());

    $html = $this->get(route('acompanamientos.show', $acompanamiento->id))->assertOk()->getContent();

    $primera = strpos($html, $nota);
    $ultima = strrpos($html, $nota);

    expect(substr_count($html, $nota))->toBe(2)
        ->and($primera)->toBeGreaterThan(strpos($html, 'Decisiones éticas del rol'))
        ->and($primera)->toBeLessThan(strpos($html, 'Resguardo de la integridad'))
        ->and($ultima)->toBeGreaterThan(strpos($html, 'Resguardo de la integridad'))
        ->and($ultima)->toBeLessThan(strpos($html, 'Para llevar a Destacado'));
});

it('incluye el ciclo sugerido, la nota de uso y el plan de mejora para imprimir', function () {
    $acompanamiento = resultadoComoDocente($this);
    $orientaciones = Orientaciones::delAnexo();

    $this->get(route('acompanamientos.show', $acompanamiento->id))
        ->assertSee('Ciclo sugerido de acompañamiento')
        ->assertSee($orientaciones->fundamentoCiclo())
        ->assertSeeInOrder($orientaciones->cicloSugerido())
        ->assertSee($orientaciones->notaDeUso())
        ->assertSee('Plan de mejora acordado')
        ->assertSee($orientaciones->planDeMejora())
        ->assertSeeInOrder(['Descriptor (n.º y nombre)', 'Acción acordada (metodológica, procedimental o formativa)', 'Responsable y apoyo', 'Fecha de revisión', 'Evidencia que se mirará']);
});

it('ofrece imprimir con reglas de impresión que ocultan el layout', function () {
    $acompanamiento = resultadoComoDocente($this);

    $this->get(route('acompanamientos.show', $acompanamiento->id))
        ->assertSee('Imprimir o guardar PDF')
        ->assertSee('window.print()', false)
        ->assertSee('@media print', false)
        ->assertSee('#barra-lateral', false)
        ->assertSee(route('acompanamientos.index'), false);
});

it('no repite consultas por indicador', function () {
    $grande = resultadoFinalizado($this);

    $pequena = Pauta::factory()->create(['nombre' => 'Pauta corta']);
    $pequena->indicadores()->attach([$this->ind[1001] => ['posicion' => 1]]);
    $corto = iniciarAcompanamiento($this, pautaId: $pequena->id);
    guardarRespuesta($this->observador, $corto, $this->ind[1001], 'en_inicio');
    $corto = finalizarAcompanamiento($this->observador, $corto);

    $consultas = function (int $id): int {
        verComo($this, $this->usuarioDocente);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('acompanamientos.show', $id))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $consultas($corto->id);
    expect($consultas($grande->id))->toBe($consultas($corto->id));
});

// --- Mis resultados (teacher list) ---------------------------------------------------------------

it('lista al docente sus acompañamientos finalizados en mis resultados', function () {
    $finalizado = resultadoFinalizado($this);
    $enCurso = iniciarAcompanamiento($this);
    verComo($this, $this->usuarioDocente);

    $this->get(route('acompanamientos.index'))
        ->assertOk()
        ->assertSee('Mis resultados')
        ->assertSee('Pauta de aula')
        ->assertSee(route('acompanamientos.show', $finalizado->id), false)
        ->assertDontSee(route('acompanamientos.show', $enCurso->id), false)
        ->assertDontSee(route('acompanamientos.create'), false);
});

it('muestra al docente sin resultados un estado vacío', function () {
    verComo($this, $this->usuarioDocente);

    $this->get(route('acompanamientos.index'))
        ->assertOk()
        ->assertSee('Mis resultados')
        ->assertSee('Aún no hay acompañamientos finalizados de sus clases.');
});

it('no lista al docente los resultados de otro docente', function () {
    $finalizado = resultadoFinalizado($this);
    [$otroDocente] = usuarioDelModulo($this->a, 'docente');
    verComo($this, $otroDocente);

    $this->get(route('acompanamientos.index'))
        ->assertOk()
        ->assertSee('Mis resultados')
        ->assertDontSee(route('acompanamientos.show', $finalizado->id), false);
});

it('muestra a un observador que también es observado sus dos secciones', function () {
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->otroObservadorId, 'docente_id' => $this->observadorId]);
    verComo($this, $this->otroObservador);
    $propio = iniciarAcompanamiento($this, $this->otroObservador, $this->observadorId);
    foreach ($this->ind as $indicadorId) {
        guardarRespuesta($this->otroObservador, $propio, $indicadorId, 'logrado');
    }
    finalizarAcompanamiento($this->otroObservador, $propio);

    verComo($this, $this->observador);

    $this->get(route('acompanamientos.index'))
        ->assertOk()
        ->assertSeeInOrder(['Mis resultados', route('acompanamientos.show', $propio->id), 'En curso', 'Finalizados'], false);
});
