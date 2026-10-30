<?php

use App\Enums\NivelLogro;
use App\Livewire\Pautas\FormularioDePauta;
use App\Livewire\Pautas\Pautas;
use App\Livewire\Pautas\VerPauta;
use App\Models\Indicador;
use App\Models\Pauta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Model::preventLazyLoading();
    escenarioDePautas($this);
});

/** A pauta of establecimiento B, created by its own administrator; A's administrator is active again afterwards. */
function pautaDeOtroEstablecimiento(object $test): int
{
    [$administradorB] = usuarioDelModulo($test->b, 'administrador');
    app()->forgetScopedInstances();
    activar($administradorB, $test->b);
    $pauta = crearPauta($administradorB, 'Pauta ajena', [$test->ind[1001]]);
    app()->forgetScopedInstances();
    activar($test->administrador, $test->a);

    return $pauta->id;
}

function comoDocente(object $test): void
{
    app()->forgetScopedInstances();
    activar($test->usuarioDocente, $test->a);
}

// --- List -----------------------------------------------------------------------------------------

it('lista a un funcionario las pautas del establecimiento con su cantidad de indicadores, sin controles de administración', function () {
    crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001], $this->ind[1002], $this->ind[2001]]);
    crearPauta($this->administrador, 'Pauta breve', [$this->ind[1003]]);
    pautaDeOtroEstablecimiento($this);
    comoDocente($this);

    $respuesta = $this->get(route('pautas.index'));

    $respuesta->assertOk()
        ->assertSee('<title>Pautas - VINKURED</title>', false)
        ->assertSeeInOrder(['Pauta breve', '1', 'Pauta de aula', '3'])
        ->assertSee('Ver')
        ->assertDontSee('Pauta ajena')
        ->assertDontSee('Nueva pauta')
        ->assertDontSee('Editar')
        ->assertDontSee('Eliminar');
    expect(substr_count($respuesta->getContent(), '<h1'))->toBe(1);
});

it('muestra al administrador del módulo los controles para crear, editar y eliminar', function () {
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);

    $this->get(route('pautas.index'))
        ->assertOk()
        ->assertSee('Nueva pauta')
        ->assertSee(route('pautas.create'), false)
        ->assertSee(route('pautas.edit', $pauta->id), false)
        ->assertSee(route('pautas.show', $pauta->id), false)
        ->assertSee('Eliminar');
});

it('muestra un estado vacío con la acción de crear solo al administrador', function () {
    $this->get(route('pautas.index'))->assertOk()->assertSee('Aún no hay pautas')->assertSee('Crear pauta');

    comoDocente($this);
    $this->get(route('pautas.index'))->assertOk()->assertSee('Aún no hay pautas')->assertDontSee('Crear pauta');
});

it('niega las pautas a quien no es funcionario y envía al invitado al inicio de sesión', function () {
    app()->forgetScopedInstances();
    activar(usuarioConMembresia($this->a), $this->a);
    $this->get(route('pautas.index'))->assertForbidden();

    auth()->logout();
    $this->get(route('pautas.index'))->assertRedirect(route('login'));
});

it('elimina una pauta tras confirmar', function () {
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);

    Livewire::test(Pautas::class)
        ->call('confirmarEliminacion', $pauta->id)
        ->assertSet('eliminando', $pauta->id)
        ->assertSee('¿Eliminar la pauta Pauta de aula?')
        ->call('eliminar')
        ->assertSet('eliminando', null)
        ->assertSee('Se eliminó la pauta Pauta de aula.');

    expect(Pauta::query()->count())->toBe(0)
        ->and(Pauta::withTrashed()->find($pauta->id)?->trashed())->toBeTrue();
});

it('cancela la eliminación sin borrar nada', function () {
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);

    Livewire::test(Pautas::class)
        ->call('confirmarEliminacion', $pauta->id)
        ->call('cancelar')
        ->assertSet('eliminando', null)
        ->call('eliminar');

    expect(Pauta::query()->count())->toBe(1);
});

it('niega eliminar a quien no es administrador del módulo', function () {
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);
    comoDocente($this);

    Livewire::test(Pautas::class)->call('confirmarEliminacion', $pauta->id)->assertForbidden();

    expect(Pauta::query()->count())->toBe(1);
});

it('no encuentra para eliminar la pauta de otro establecimiento', function () {
    $ajena = pautaDeOtroEstablecimiento($this);

    Livewire::test(Pautas::class)->call('confirmarEliminacion', $ajena)->assertNotFound();

    expect(Pauta::sinEstablecimiento()->find($ajena)?->trashed())->toBeFalse();
});

// --- Create / edit --------------------------------------------------------------------------------

it('muestra el catálogo agrupado por pauta base y ámbito en el formulario', function () {
    $this->get(route('pautas.create'))
        ->assertOk()
        ->assertSee('<title>Nueva pauta - VINKURED</title>', false)
        ->assertSee('maxlength="120"', false)
        ->assertSeeInOrder(['Base uno', 'Ámbito 1.1', '1001. Indicador 1001', '1002. Indicador 1002', 'Ámbito 1.2', '1003. Indicador 1003', 'Base dos', 'Ámbito 2.1', '2001. Indicador 2001'])
        ->assertSee('Seleccionar todos')
        ->assertSee('Quitar todos');
});

it('crea una pauta y lleva a su vista con un mensaje de éxito', function () {
    Livewire::test(FormularioDePauta::class)
        ->set('nombre', 'Pauta de aula')
        ->set('indicadores', [(string) $this->ind[2001], (string) $this->ind[1001]])
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('pautas.show', Pauta::query()->sole()->id));

    expect(Pauta::query()->sole()->nombre)->toBe('Pauta de aula')
        ->and(indicadoresGuardados(Pauta::query()->sole()->id))->toBe([$this->ind[1001], $this->ind[2001]])
        ->and(session('estado'))->toBe('Se creó la pauta Pauta de aula.');
});

it('muestra los errores de nombre e indicadores junto a sus campos', function () {
    Livewire::test(FormularioDePauta::class)
        ->set('nombre', '   ')
        ->call('guardar')
        ->assertHasErrors(['nombre', 'indicadores'])
        ->assertSee('Escribir un nombre para la pauta.')
        ->assertSee('Elegir al menos un indicador.')
        ->assertSeeHtml('aria-invalid="true"')
        ->assertNoRedirect();

    crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);

    Livewire::test(FormularioDePauta::class)
        ->set('nombre', 'PAUTA DE AULA')
        ->set('indicadores', [(string) $this->ind[1001]])
        ->call('guardar')
        ->assertHasErrors(['nombre'])
        ->assertSee('Ya existe una pauta con este nombre.');

    expect(Pauta::query()->count())->toBe(1);
});

it('selecciona y quita todos los indicadores de una pauta base sin tocar los de otra', function () {
    $baseUno = DB::table('pautas_base')->where('numero', 9001)->value('id');

    Livewire::test(FormularioDePauta::class)
        ->assertSee('0 indicadores elegidos')
        ->set('indicadores', [(string) $this->ind[2001], (string) $this->ind[1002]])
        ->call('seleccionarTodos', $baseUno)
        ->assertSet('indicadores', array_map('strval', [$this->ind[2001], $this->ind[1002], $this->ind[1001], $this->ind[1003]]))
        ->assertSee('4 indicadores elegidos')
        ->call('quitarTodos', $baseUno)
        ->assertSet('indicadores', [(string) $this->ind[2001]])
        ->assertSee('1 indicador elegido');
});

it('precarga la pauta al editar y guarda los cambios', function () {
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[2001], $this->ind[1002]]);

    $this->get(route('pautas.edit', $pauta->id))->assertOk()->assertSee('<title>Editar pauta - VINKURED</title>', false);

    Livewire::test(FormularioDePauta::class, ['pauta' => $pauta->id])
        ->assertSet('nombre', 'Pauta de aula')
        ->assertSet('indicadores', [(string) $this->ind[1002], (string) $this->ind[2001]])
        ->set('nombre', 'Pauta renovada')
        ->set('indicadores', [(string) $this->ind[1003]])
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('pautas.show', $pauta->id));

    expect($pauta->fresh()->nombre)->toBe('Pauta renovada')
        ->and(indicadoresGuardados($pauta->id))->toBe([$this->ind[1003]])
        ->and(session('estado'))->toBe('Se guardaron los cambios de la pauta Pauta renovada.');
});

it('niega crear y editar a quien no es administrador del módulo', function () {
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);
    comoDocente($this);

    $this->get(route('pautas.create'))->assertForbidden();
    $this->get(route('pautas.edit', $pauta->id))->assertForbidden();
    Livewire::test(FormularioDePauta::class)->assertForbidden();
    Livewire::test(FormularioDePauta::class, ['pauta' => $pauta->id])->assertForbidden();
});

it('no encuentra la pauta de otro establecimiento ni una eliminada', function () {
    $ajena = pautaDeOtroEstablecimiento($this);
    $eliminada = crearPauta($this->administrador, 'Eliminada', [$this->ind[1001]]);
    eliminarPauta($this->administrador, $eliminada->id);

    foreach ([$ajena, $eliminada->id, 'abc'] as $id) {
        $this->get(route('pautas.edit', $id))->assertNotFound();
        $this->get(route('pautas.show', $id))->assertNotFound();
    }
});

// --- View / print ---------------------------------------------------------------------------------

it('muestra la pauta con la tabla de niveles y cada indicador con sus descriptores, por ámbito y en orden', function () {
    Indicador::query()->whereKey($this->ind[1002])->update([
        'nivel_en_inicio' => 'Descriptor inicio 1002', 'nivel_en_desarrollo' => 'Descriptor desarrollo 1002',
        'nivel_logrado' => 'Descriptor logrado 1002', 'nivel_destacado' => 'Descriptor destacado 1002',
    ]);
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[2001], $this->ind[1003], $this->ind[1002]]);
    comoDocente($this);

    $respuesta = $this->get(route('pautas.show', $pauta->id));

    $niveles = NivelLogro::tablaSignificados();
    $respuesta->assertOk()
        ->assertSee('<title>Pauta de aula - VINKURED</title>', false)
        ->assertSee('Imprimir o guardar PDF')
        ->assertSeeHtml('window.print()')
        ->assertDontSee(route('pautas.edit', $pauta->id), false)
        ->assertSeeInOrder([
            'Significado de los niveles',
            ...array_merge(...array_map(fn (array $nivel): array => [$nivel['etiqueta'], $nivel['significado']], $niveles)),
        ])
        ->assertSeeInOrder([
            'Ámbito 1.1', '1002. Indicador 1002',
            'En inicio', 'Descriptor inicio 1002', 'En desarrollo', 'Descriptor desarrollo 1002',
            'Logrado', 'Descriptor logrado 1002', 'Destacado', 'Descriptor destacado 1002',
            'Ámbito 1.2', '1003. Indicador 1003',
            'Ámbito 2.1', '2001. Indicador 2001',
        ])
        ->assertSeeHtml('scope="row"')
        ->assertSeeHtml('<caption');
    expect(substr_count($respuesta->getContent(), '<h1'))->toBe(1);
});

it('oculta al imprimir la barra lateral, la barra superior y el enlace de salto del layout', function () {
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);

    $this->get(route('pautas.show', $pauta->id))
        ->assertOk()
        ->assertSeeHtml('@media print')
        ->assertSeeHtml('#barra-lateral')
        ->assertSeeHtml('a[href="#contenido"]')
        ->assertSeeHtml(':has(> #contenido) > header');
});

it('muestra a un administrador el enlace para editar la pauta', function () {
    $pauta = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);

    Livewire::test(VerPauta::class, ['pauta' => $pauta->id])
        ->assertSee('Imprimir o guardar PDF')
        ->assertSee(route('pautas.edit', $pauta->id), false);
});
