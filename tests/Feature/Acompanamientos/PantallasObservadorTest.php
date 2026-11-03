<?php

use App\Enums\EstadoAcompanamiento;
use App\Livewire\Acompanamientos\Acompanamientos;
use App\Livewire\Acompanamientos\NuevoAcompanamiento;
use App\Livewire\Acompanamientos\ObservarAcompanamiento;
use App\Mail\AcompanamientoFinalizado;
use App\Models\Acompanamiento;
use App\Models\AsignacionAcompanamiento;
use App\Models\Funcionario;
use App\Models\Pauta;
use App\Models\RespuestaAcompanamiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Model::preventLazyLoading();
    Mail::fake();
    escenarioDeAcompanamientos($this);
    darNombreAlFuncionario($this->docenteId, 'Beatriz', 'Rojas');
    darNombreAlFuncionario($this->docenteSinAsignarId, 'Carmen', 'Soto');
    darNombreAlFuncionario($this->observadorId, 'Daniela', 'Tapia');
    darNombreAlFuncionario($this->otroObservadorId, 'Elena', 'Vera');
});

/** Gives the persona behind a funcionario a distinct name, so lists can be told apart. */
function darNombreAlFuncionario(int $funcionarioId, string $nombres, string $apellido): void
{
    DB::table('personas')
        ->where('id', DB::table('funcionarios')->where('id', $funcionarioId)->value('persona_id'))
        ->update(['nombres' => $nombres, 'primer_apellido' => $apellido]);
}

function comoUsuario(object $test, $usuario, ?int $establecimientoId = null): void
{
    app()->forgetScopedInstances();
    activar($usuario, $establecimientoId ?? $test->a);
}

/** A finished acompañamiento of the scenario observer. */
function acompanamientoFinalizado(object $test): Acompanamiento
{
    $acompanamiento = iniciarAcompanamiento($test);
    responderTodo($test, $acompanamiento);

    return finalizarAcompanamiento($test->observador, $acompanamiento);
}

/** An acompañamiento of establecimiento B; the scenario observer of A is active again afterwards. */
function acompanamientoDeOtroEstablecimiento(object $test): int
{
    [$administradorB] = usuarioDelModulo($test->b, 'administrador');
    comoUsuario($test, $administradorB, $test->b);
    $id = Acompanamiento::factory()->create()->id;
    comoUsuario($test, $test->observador);

    return $id;
}

// --- Mis acompañamientos --------------------------------------------------------------------------

it('lista al observador sus acompañamientos separados en curso y finalizados', function () {
    $finalizado = acompanamientoFinalizado($this);
    $enCurso = iniciarAcompanamiento($this);

    $respuesta = $this->get(route('acompanamientos.index'));

    $respuesta->assertOk()
        ->assertSee('<title>Mis acompañamientos - VINKURED</title>', false)
        ->assertSeeInOrder(['En curso', 'Beatriz Rojas', 'Pauta de aula', 'Lenguaje', 'Continuar', 'Finalizados', 'Beatriz Rojas', 'Finalizado'])
        ->assertSee(route('acompanamientos.edit', $enCurso->id), false)
        ->assertSee(route('acompanamientos.show', $finalizado->id), false)
        ->assertDontSee(route('acompanamientos.edit', $finalizado->id), false)
        ->assertSee(route('acompanamientos.create'), false);
    expect(substr_count($respuesta->getContent(), '<h1'))->toBe(1);
});

it('no lista al observador los acompañamientos de otro observador', function () {
    asignarDocenteAObservador($this->otroObservadorId, $this->docenteId);
    comoUsuario($this, $this->otroObservador);
    iniciarAcompanamiento($this, usuario: $this->otroObservador);
    comoUsuario($this, $this->observador);

    $this->get(route('acompanamientos.index'))
        ->assertOk()
        ->assertDontSee('Beatriz Rojas')
        ->assertSee('Aún no hay acompañamientos en curso.');
});

it('descarta un acompañamiento en curso tras confirmarlo en el modal', function () {
    $acompanamiento = iniciarAcompanamiento($this);
    guardarRespuesta($this->observador, $acompanamiento, $this->ind[1001], 'logrado');

    Livewire::test(Acompanamientos::class)
        ->call('confirmarDescarte', $acompanamiento->id)
        ->assertSet('descartando', $acompanamiento->id)
        ->assertSee('¿Descartar el acompañamiento de Beatriz Rojas?')
        ->call('descartar')
        ->assertSet('descartando', null)
        ->assertSee('Se descartó el acompañamiento de Beatriz Rojas.');

    expect(Acompanamiento::query()->count())->toBe(0)
        ->and(RespuestaAcompanamiento::query()->count())->toBe(0);
});

it('no descarta tras cancelar el modal', function () {
    $acompanamiento = iniciarAcompanamiento($this);

    Livewire::test(Acompanamientos::class)
        ->call('confirmarDescarte', $acompanamiento->id)
        ->call('cancelar')
        ->assertSet('descartando', null)
        ->call('descartar');

    expect(Acompanamiento::query()->count())->toBe(1);
});

it('no deja descartar el acompañamiento de otro observador ni uno finalizado', function () {
    $acompanamiento = iniciarAcompanamiento($this);
    $finalizado = acompanamientoFinalizado($this);
    comoUsuario($this, $this->otroObservador);

    Livewire::test(Acompanamientos::class)->call('confirmarDescarte', $acompanamiento->id)->assertForbidden();

    comoUsuario($this, $this->observador);
    Livewire::test(Acompanamientos::class)->call('confirmarDescarte', $finalizado->id)->assertForbidden();

    expect(Acompanamiento::query()->count())->toBe(2);
});

it('no encuentra para descartar un acompañamiento de otro establecimiento', function () {
    $ajeno = acompanamientoDeOtroEstablecimiento($this);

    Livewire::test(Acompanamientos::class)->call('confirmarDescarte', $ajeno)->assertNotFound();
});

it('muestra al administrador del módulo todos los acompañamientos del establecimiento al elegirlo', function () {
    $acompanamiento = iniciarAcompanamiento($this);
    [$administrador] = usuarioDelModulo($this->a, 'administrador');
    comoUsuario($this, $administrador);

    Livewire::test(Acompanamientos::class)
        ->assertDontSee('Beatriz Rojas')
        ->set('alcance', 'establecimiento')
        ->assertSee('Beatriz Rojas')
        ->assertSee('Daniela Tapia')
        ->assertDontSee(route('acompanamientos.edit', $acompanamiento->id), false);

    comoUsuario($this, $this->observador);
    Livewire::test(Acompanamientos::class)->set('alcance', 'establecimiento')->assertDontSee('Todos del establecimiento');
});

it('muestra a un docente que no observa un estado vacío que lo lleva a sus resultados', function () {
    comoUsuario($this, $this->usuarioDocente);

    $this->get(route('acompanamientos.index'))
        ->assertOk()
        ->assertSee('Sin acompañamientos por observar')
        ->assertDontSee(route('acompanamientos.create'), false);
});

// --- Nuevo acompañamiento -------------------------------------------------------------------------

it('ofrece solo los docentes asignados al observador y las pautas con indicadores', function () {
    Pauta::factory()->create(['nombre' => 'Pauta vacía']);

    $this->get(route('acompanamientos.create'))
        ->assertOk()
        ->assertSee('<title>Nuevo acompañamiento - VINKURED</title>', false)
        ->assertSee('Beatriz Rojas')
        ->assertDontSee('Carmen Soto')
        ->assertSee('Pauta de aula')
        ->assertDontSee('Pauta vacía')
        ->assertSee('Lenguaje');
});

it('inicia el acompañamiento y lleva al formulario de observación', function () {
    Livewire::test(NuevoAcompanamiento::class)
        ->set('docente', (string) $this->docenteId)
        ->set('pauta', (string) $this->pauta->id)
        ->set('curso', (string) $this->cursoId)
        ->set('asignatura', (string) $this->asignaturaId)
        ->call('iniciar')
        ->assertHasNoErrors()
        ->assertRedirect(route('acompanamientos.edit', Acompanamiento::query()->sole()->id));

    expect(Acompanamiento::query()->sole())
        ->observador_id->toBe($this->observadorId)
        ->docente_id->toBe($this->docenteId);
});

it('muestra los errores de los campos sin elegir y no inicia nada', function () {
    Livewire::test(NuevoAcompanamiento::class)
        ->call('iniciar')
        ->assertHasErrors(['docente', 'pauta', 'curso', 'asignatura'])
        ->assertSee('Elegir el docente.')
        ->assertSee('Elegir la pauta.')
        ->assertSeeHtml('aria-invalid="true"')
        ->assertNoRedirect();

    expect(Acompanamiento::query()->count())->toBe(0);
});

it('rechaza un docente no asignado y un curso de otro establecimiento', function () {
    Livewire::test(NuevoAcompanamiento::class)
        ->set('docente', (string) $this->docenteSinAsignarId)
        ->set('pauta', (string) $this->pauta->id)
        ->set('curso', (string) $this->cursoId)
        ->set('asignatura', (string) $this->asignaturaId)
        ->call('iniciar')
        ->assertHasErrors('docente')
        ->assertNoRedirect();

    Livewire::test(NuevoAcompanamiento::class)
        ->set('docente', (string) $this->docenteId)
        ->set('pauta', (string) $this->pauta->id)
        ->set('curso', (string) $this->cursoAjenoId)
        ->set('asignatura', (string) $this->asignaturaId)
        ->call('iniciar')
        ->assertHasErrors('curso')
        ->assertNoRedirect();

    expect(Acompanamiento::query()->count())->toBe(0);
});

it('preselecciona el curso y la asignatura cuando el docente tiene una sola asignación docente', function () {
    DB::table('asignaciones_docentes')->insert([
        'establecimiento_id' => $this->a, 'funcionario_id' => $this->docenteId,
        'curso_id' => $this->cursoId, 'asignatura_id' => $this->asignaturaId, 'es_jefatura' => false,
    ]);

    Livewire::test(NuevoAcompanamiento::class)
        ->set('docente', (string) $this->docenteId)
        ->assertSet('curso', (string) $this->cursoId)
        ->assertSet('asignatura', (string) $this->asignaturaId);
});

it('no deja abrir la pantalla nueva a quien no observa', function () {
    comoUsuario($this, $this->usuarioDocente);

    $this->get(route('acompanamientos.create'))->assertForbidden();
});

// --- Formulario de observación --------------------------------------------------------------------

it('muestra el encabezado, el significado de los niveles y los indicadores por ámbito', function () {
    $acompanamiento = iniciarAcompanamiento($this);

    $this->get(route('acompanamientos.edit', $acompanamiento->id))
        ->assertOk()
        ->assertSee('Beatriz Rojas')
        ->assertSee('Pauta de aula')
        ->assertSee('Lenguaje')
        ->assertSee('Significado de los niveles')
        ->assertSeeInOrder(['Clima de aula', '1001', 'En inicio', 'En desarrollo', 'Logrado', 'Destacado', 'Gestión de la clase', '1002'])
        ->assertSee('0 de 2 indicadores con nivel')
        ->assertSeeHtml('maxlength="2000"');
});

it('guarda el nivel de un indicador apenas se elige', function () {
    $acompanamiento = iniciarAcompanamiento($this);

    Livewire::test(ObservarAcompanamiento::class, ['acompanamiento' => $acompanamiento->id])
        ->set("niveles.{$this->ind[1001]}", 'logrado')
        ->assertHasNoErrors()
        ->assertSee('1 de 2 indicadores con nivel')
        ->assertSee('Nivel del indicador 1001 guardado.');

    expect(RespuestaAcompanamiento::query()->sole())
        ->indicador_id->toBe($this->ind[1001])
        ->nivel->value->toBe('logrado');
});

it('guarda la retroalimentación personal con el nivel, aunque se escriba antes de elegirlo', function () {
    $acompanamiento = iniciarAcompanamiento($this);

    $componente = Livewire::test(ObservarAcompanamiento::class, ['acompanamiento' => $acompanamiento->id])
        ->set("retroalimentaciones.{$this->ind[1001]}", 'Buen inicio de clase.');

    expect(RespuestaAcompanamiento::query()->count())->toBe(0);

    $componente->set("niveles.{$this->ind[1001]}", 'destacado')
        ->set("retroalimentaciones.{$this->ind[1001]}", '  Muy buen cierre.  ')
        ->assertHasNoErrors();

    expect(RespuestaAcompanamiento::query()->sole()->retroalimentacion_personal)->toBe('Muy buen cierre.');
});

it('rechaza un nivel que no es de la pauta', function () {
    $acompanamiento = iniciarAcompanamiento($this);

    Livewire::test(ObservarAcompanamiento::class, ['acompanamiento' => $acompanamiento->id])
        ->set("niveles.{$this->ind[1001]}", 'excelente')
        ->assertHasErrors("niveles.{$this->ind[1001]}");

    expect(RespuestaAcompanamiento::query()->count())->toBe(0);
});

it('al finalizar con indicadores sin nivel muestra cuáles faltan y no finaliza', function () {
    $acompanamiento = iniciarAcompanamiento($this);
    guardarRespuesta($this->observador, $acompanamiento, $this->ind[1001], 'logrado');

    Livewire::test(ObservarAcompanamiento::class, ['acompanamiento' => $acompanamiento->id])
        ->call('confirmarFinalizacion')
        ->assertSet('confirmando', true)
        ->assertSee('se avisa por correo')
        ->call('finalizar')
        ->assertSet('confirmando', false)
        ->assertHasErrors('respuestas')
        ->assertSee('Falta asignar el nivel del indicador 1002.')
        ->assertNoRedirect();

    expect($acompanamiento->fresh()->estado)->toBe(EstadoAcompanamiento::EnCurso);
    Mail::assertNothingQueued();
});

it('finaliza con todos los niveles, avisa al docente y vuelve a la lista', function () {
    $acompanamiento = iniciarAcompanamiento($this);
    responderTodo($this, $acompanamiento);

    Livewire::test(ObservarAcompanamiento::class, ['acompanamiento' => $acompanamiento->id])
        ->call('confirmarFinalizacion')
        ->call('finalizar')
        ->assertHasNoErrors()
        ->assertRedirect(route('acompanamientos.index'));

    expect($acompanamiento->fresh()->estado)->toBe(EstadoAcompanamiento::Finalizado)
        ->and(session('estado'))->toBe('Se finalizó el acompañamiento de Beatriz Rojas. El resultado ya está disponible para el docente.');
    Mail::assertQueued(AcompanamientoFinalizado::class, 1);
});

it('lleva al resultado al abrir un acompañamiento ya finalizado', function () {
    $finalizado = acompanamientoFinalizado($this);

    Livewire::test(ObservarAcompanamiento::class, ['acompanamiento' => $finalizado->id])
        ->assertRedirect(route('acompanamientos.show', $finalizado->id));
});

it('no deja abrir el formulario a quien no observa ni a otro observador', function () {
    $acompanamiento = iniciarAcompanamiento($this);

    comoUsuario($this, $this->usuarioDocente);
    $this->get(route('acompanamientos.edit', $acompanamiento->id))->assertForbidden();

    comoUsuario($this, $this->otroObservador);
    $this->get(route('acompanamientos.edit', $acompanamiento->id))->assertForbidden();
});

it('no encuentra el formulario de un acompañamiento de otro establecimiento', function () {
    $ajeno = acompanamientoDeOtroEstablecimiento($this);

    $this->get(route('acompanamientos.edit', $ajeno))->assertNotFound();
});

function asignarDocenteAObservador(int $observadorId, int $docenteId): void
{
    AsignacionAcompanamiento::factory()->create(['observador_id' => $observadorId, 'docente_id' => $docenteId]);
}
