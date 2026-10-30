<?php

use App\Livewire\Observadores\Observadores;
use App\Models\AsignacionAcompanamiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Model::preventLazyLoading();
    escenarioDeObservadores($this);
    $this->habilitadoId = funcionarioConFuncion($this->a, 'docente');
    habilitarObservador($this->a, $this->habilitadoId);
    $this->observadorAjenoId = funcionarioConFuncion($this->b, 'docente');
    habilitarObservador($this->b, $this->observadorAjenoId);

    // Fictitious names, so each row can be told apart.
    nombrarFuncionario($this->administradorId, 'Amalia', 'Directora');
    nombrarFuncionario($this->directivoId, 'Bruno', 'Subdirector');
    nombrarFuncionario($this->habilitadoId, 'Carla', 'Habilitada');
    nombrarFuncionario($this->docenteId, 'Diego', 'Docente');
    nombrarFuncionario($this->otroDocenteId, 'Elena', 'Docente');
    nombrarFuncionario($this->asistenteId, 'Fabián', 'Asistente');
    nombrarFuncionario($this->docenteAjenoId, 'Gonzalo', 'Ajeno');
    nombrarFuncionario($this->observadorAjenoId, 'Hilda', 'Ajena');
});

function nombrarFuncionario(int $funcionarioId, string $nombres, string $apellido): void
{
    $personaId = DB::table('funcionarios')->where('id', $funcionarioId)->value('persona_id');
    DB::table('personas')->where('id', $personaId)->update(['nombres' => $nombres, 'primer_apellido' => $apellido]);
}

it('muestra al administrador del módulo los observadores del establecimiento activo, con los automáticos marcados', function () {
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->habilitadoId, 'docente_id' => $this->docenteId]);

    $respuesta = $this->get(route('observadores.index'));

    $respuesta->assertOk()
        ->assertSee('<title>Observadores - VINKURED</title>', false)
        ->assertSeeInOrder(['Amalia Directora', 'Automático', 'Bruno Subdirector', 'Automático', 'Carla Habilitada'])
        ->assertDontSee('Hilda Ajena');
    expect(substr_count($respuesta->getContent(), '<h1'))->toBe(1)
        ->and(filaDeObservador($respuesta->getContent(), 'Carla Habilitada'))->not->toContain('Automático')
        ->toMatch('/>\s*1\s*</');
});

it('niega la pantalla a quien no es administrador del módulo', function () {
    activar($this->usuarioDocente, $this->a);

    $this->get(route('observadores.index'))->assertForbidden();
    Livewire::test(Observadores::class)->assertForbidden();
});

it('envía al inicio de sesión a un invitado', function () {
    auth()->logout();

    $this->get(route('observadores.index'))->assertRedirect(route('login'));
});

it('habilita como observador a un funcionario vigente elegido de la lista', function () {
    Livewire::test(Observadores::class)
        ->assertSeeHtml('value="'.$this->otroDocenteId.'"')
        ->assertDontSeeHtml('<option value="'.$this->habilitadoId.'"')
        ->assertDontSeeHtml('<option value="'.$this->directivoId.'"')
        ->set('nuevoObservador', (string) $this->otroDocenteId)
        ->call('habilitar')
        ->assertHasNoErrors()
        ->assertSet('seleccionado', $this->otroDocenteId)
        ->assertSet('nuevoObservador', '')
        ->assertSee('Se habilitó a Elena Docente como observador.');

    expect(esObservadorHabilitado($this->otroDocenteId))->toBeTrue();
});

it('pide elegir un funcionario antes de habilitar', function () {
    Livewire::test(Observadores::class)
        ->call('habilitar')
        ->assertHasErrors(['nuevoObservador'])
        ->assertSee('Elegir el funcionario que se va a habilitar.');
});

it('no habilita a un funcionario de otro establecimiento', function () {
    Livewire::test(Observadores::class)
        ->assertDontSeeHtml('value="'.$this->docenteAjenoId.'"')
        ->set('nuevoObservador', (string) $this->docenteAjenoId)
        ->call('habilitar')
        ->assertHasErrors(['nuevoObservador']);

    expect(esObservadorHabilitado($this->docenteAjenoId))->toBeFalse();
});

it('muestra los docentes del observador elegido, marcando los que acompaña, y guarda la selección', function () {
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->habilitadoId, 'docente_id' => $this->docenteId]);

    Livewire::test(Observadores::class)
        ->call('seleccionar', $this->habilitadoId)
        ->assertSet('docentes', [(string) $this->docenteId])
        ->assertSeeHtml('aria-current="true"')
        ->assertSee('Viendo docentes')
        ->assertSee('Docentes que acompaña Carla Habilitada')
        ->assertSeeHtml(casillaDeDocente($this->otroDocenteId))
        ->assertDontSeeHtml(casillaDeDocente($this->habilitadoId))
        ->assertDontSeeHtml(casillaDeDocente($this->asistenteId))
        ->set('docentes', [(string) $this->otroDocenteId])
        ->call('guardar')
        ->assertSee('Docentes de Carla Habilitada guardados.');

    expect(docentesDe($this->habilitadoId))->toBe([$this->otroDocenteId]);
});

it('informa sin guardar cuando un docente elegido ya no se puede asignar', function () {
    Livewire::test(Observadores::class)
        ->call('seleccionar', $this->habilitadoId)
        ->set('docentes', [(string) $this->asistenteId])
        ->call('guardar')
        ->assertSee('No se guardó');

    expect(docentesDe($this->habilitadoId))->toBe([]);
});

it('quita a un observador habilitado tras confirmar, con sus asignaciones', function () {
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->habilitadoId, 'docente_id' => $this->docenteId]);

    Livewire::test(Observadores::class)
        ->call('seleccionar', $this->habilitadoId)
        ->assertSee('Quitar como observador')
        ->call('confirmarQuita')
        ->assertSet('confirmandoQuita', true)
        ->call('quitar')
        ->assertSet('seleccionado', null)
        ->assertSet('confirmandoQuita', false)
        ->assertSee('Carla Habilitada ya no es observador.');

    expect(esObservadorHabilitado($this->habilitadoId))->toBeFalse()
        ->and(docentesDe($this->habilitadoId))->toBe([]);
});

it('no ofrece quitar a un observador automático ni lo quita', function () {
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->directivoId, 'docente_id' => $this->docenteId]);

    Livewire::test(Observadores::class)
        ->call('seleccionar', $this->directivoId)
        ->assertSee('Docentes que acompaña Bruno Subdirector')
        ->assertDontSee('Quitar como observador')
        ->call('confirmarQuita')
        ->assertSet('confirmandoQuita', false);

    expect(docentesDe($this->directivoId))->toBe([$this->docenteId]);
});

it('no deja elegir a un observador de otro establecimiento', function () {
    Livewire::test(Observadores::class)
        ->call('seleccionar', $this->observadorAjenoId)
        ->assertNotFound();
});

it('revisa el permiso en cada acción', function () {
    $componente = Livewire::test(Observadores::class);
    DB::table('funcionario_funciones')->where('funcionario_id', $this->administradorId)->delete();
    app()->forgetScopedInstances();
    activar($this->administrador, $this->a);

    $componente->set('nuevoObservador', (string) $this->otroDocenteId)->call('habilitar')->assertForbidden();

    expect(esObservadorHabilitado($this->otroDocenteId))->toBeFalse();
});

it('muestra los estados vacíos sin observadores habilitados ni docentes', function () {
    DB::table('observadores')->where('funcionario_id', $this->habilitadoId)->delete();
    DB::table('funcionarios')->whereIn('id', [$this->docenteId, $this->otroDocenteId, $this->habilitadoId])
        ->update(['fecha_termino' => now()->subDay()->toDateString()]);

    Livewire::test(Observadores::class)
        ->assertSee('Aún no hay observadores habilitados.')
        ->call('seleccionar', $this->directivoId)
        ->assertSee('No hay docentes vigentes para asignar en este establecimiento.')
        ->assertDontSee('Guardar docentes');
});

function casillaDeDocente(int $funcionarioId): string
{
    return 'type="checkbox" wire:model="docentes" value="'.$funcionarioId.'"';
}

/** HTML of the table row that contains $texto. */
function filaDeObservador(string $html, string $texto): string
{
    preg_match_all('/<tr\b[^>]*>.*?<\/tr>/s', $html, $filas);

    foreach ($filas[0] as $fila) {
        if (str_contains($fila, $texto)) {
            return $fila;
        }
    }

    return '';
}
