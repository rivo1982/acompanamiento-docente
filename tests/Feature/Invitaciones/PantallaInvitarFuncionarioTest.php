<?php

use App\Jobs\EnviarInvitacion;
use App\Livewire\Invitaciones\NuevaInvitacion;
use App\Models\Membresia;
use App\Models\Usuario;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(FuncionesGenericasSeeder::class);
    $this->a = newEstablecimiento('1000', 'Colegio Ficticio A');
    $this->b = newEstablecimiento('2000', 'Colegio Ficticio B');
    Queue::fake();
});

/** Logged-in Administrador of $estId with that establecimiento active. */
function administradorConSesion(int $estId): Usuario
{
    return Usuario::query()->findOrFail(administradorDe($estId)->usuario_id);
}

/**
 * Form filled with valid fictitious data (overridable by property name).
 *
 * @param  array<string, string>  $cambios
 */
function formularioDeInvitacion(array $cambios = []): Testable
{
    $datos = [
        'rut' => '12.345.678-5',
        'nombres' => 'Ana María',
        'primer_apellido' => 'Pérez',
        'segundo_apellido' => 'Soto',
        'correo' => 'ana.perez@example.test',
        'telefono' => '+56 9 1234 5678',
        'funcion' => 'docente',
        ...$cambios,
    ];

    $componente = Livewire::test(NuevaInvitacion::class);
    foreach ($datos as $propiedad => $valor) {
        $componente->set($propiedad, $valor);
    }

    return $componente;
}

function invitacionesDe(int $estId): int
{
    return Membresia::query()->where('establecimiento_id', $estId)->where('estado', 'invitada')->count();
}

it('shows the Administrador the invite form with the spreadsheet fields in order', function () {
    administradorConSesion($this->a);

    $respuesta = $this->get(route('invitaciones.create'));

    $respuesta->assertOk()
        ->assertSee('<title>Invitar funcionario - VINKURED</title>', false)
        ->assertSeeInOrder(['RUT (obligatorio)', 'Nombres (obligatorio)', 'Primer apellido (obligatorio)', 'Segundo apellido', 'Correo (obligatorio)', 'Teléfono', 'Función (obligatorio)'])
        ->assertSeeInOrder(['Docente', 'Directivo', 'Asistente de la educación', 'Profesional de apoyo'])
        ->assertSee('Enviar invitación');
    $html = $respuesta->getContent();
    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toMatch('/<form\b[^>]*method="post"[^>]*wire:submit="invitar"/')
        ->and($html)->toContain('type="email"')
        ->and($html)->toContain('type="tel"');
});

it('forbids the invite page and its action to a user who is not Administrador', function () {
    [$usuario] = usuarioConRoles($this->a);
    activar($usuario, $this->a);

    $this->get(route('invitaciones.create'))->assertForbidden();
    Livewire::test(NuevaInvitacion::class)->assertForbidden();
});

it('sends a guest to the login and a user without an active establecimiento to the selection', function () {
    $this->get(route('invitaciones.create'))->assertRedirect(route('login'));

    $usuario = usuarioConMembresia($this->a);
    usuarioConMembresia($this->b);
    newMembresia($this->b, $usuario->id, null, ['estado' => 'activa']);
    sinContexto($usuario);

    $this->get(route('invitaciones.create'))->assertRedirect(route('establecimientos.seleccionar'));
});

it('re-checks the Administrador on submit and invites nobody when the role was removed', function () {
    $usuario = administradorConSesion($this->a);
    $componente = formularioDeInvitacion();
    DB::table('membresias')->where('usuario_id', $usuario->id)->where('establecimiento_id', $this->a)->update(['es_administrador' => false]);

    $componente->call('invitar')->assertForbidden();

    expect(invitacionesDe($this->a))->toBe(0);
    Queue::assertNothingPushed();
});

it('shows the spreadsheet validation messages next to each field and invites nobody', function (array $cambios, string $campo, string $mensaje) {
    administradorConSesion($this->a);

    formularioDeInvitacion($cambios)
        ->call('invitar')
        ->assertHasErrors($campo)
        ->assertSee($mensaje)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee($campo === 'rut' ? 'aria-describedby="rut-ayuda rut-error"' : 'aria-describedby="'.$campo.'-error"', false)
        ->assertJs("document.getElementById('{$campo}').focus()");

    expect(invitacionesDe($this->a))->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'RUT con dígito verificador incorrecto' => [['rut' => '12.345.678-9'], 'rut', 'El dígito verificador del RUT no corresponde.'],
    'RUT vacío' => [['rut' => ''], 'rut', 'El RUT es obligatorio.'],
    'nombres vacíos' => [['nombres' => '  '], 'nombres', 'Los nombres son obligatorios.'],
    'primer apellido vacío' => [['primer_apellido' => ''], 'primer_apellido', 'El primer apellido es obligatorio.'],
    'correo vacío' => [['correo' => ''], 'correo', 'El correo es obligatorio.'],
    'correo inválido' => [['correo' => 'no-es-correo'], 'correo', 'El correo no es válido.'],
    'teléfono con letras' => [['telefono' => '9 abc'], 'telefono', 'El teléfono solo puede tener números, espacios y los signos + - ( ).'],
    'función vacía' => [['funcion' => ''], 'funcion', 'La función es obligatoria.'],
    'función manipulada' => [['funcion' => 'rector'], 'funcion', 'La función debe ser docente, directivo, asistente o profesional de apoyo.'],
]);

it('shows every invalid field at once and focuses the first one', function () {
    administradorConSesion($this->a);

    formularioDeInvitacion(['rut' => '', 'correo' => 'x', 'funcion' => ''])
        ->call('invitar')
        ->assertHasErrors(['rut', 'correo', 'funcion'])
        ->assertJs("document.getElementById('rut').focus()");
});

it('invites the person, queues the mail and shows a success message, then clears the form', function () {
    administradorConSesion($this->a);

    formularioDeInvitacion()
        ->call('invitar')
        ->assertHasNoErrors()
        ->assertSee('Invitación enviada a Ana María Pérez.')
        ->assertSee('role="status"', false)
        ->assertSet('rut', '')
        ->assertSet('correo', '')
        ->assertSet('funcion', '')
        ->assertJs("document.getElementById('mensaje-invitacion').focus()");

    $membresia = Membresia::query()->where('establecimiento_id', $this->a)->where('estado', 'invitada')->sole();
    expect(Usuario::query()->findOrFail($membresia->usuario_id)->correo)->toBe('ana.perez@example.test');
    Queue::assertPushed(EnviarInvitacion::class, 1);
});

it('shows the same success message when the correo already has an account in another establecimiento', function () {
    usuarioConMembresia($this->b, 'activa', ['correo' => 'ya.existe@example.test']);
    administradorConSesion($this->a);

    $nueva = formularioDeInvitacion(['rut' => rutDeInvitado(11111111)->formateado(), 'correo' => 'nueva.persona@example.test'])->call('invitar');
    $existente = formularioDeInvitacion(['rut' => rutDeInvitado(22222222)->formateado(), 'correo' => 'ya.existe@example.test'])->call('invitar');

    expect($existente->get('mensaje'))->toBe($nueva->get('mensaje'))
        ->and($existente->get('tipoDeMensaje'))->toBe($nueva->get('tipoDeMensaje'))
        ->and($existente->get('mensaje'))->toBe('Invitación enviada a Ana María Pérez.');
    $existente->assertDontSee('ya.existe@example.test')->assertDontSee('Colegio Ficticio B');
    Queue::assertPushed(EnviarInvitacion::class, 2);
});

it('explains a conflict inside this establecimiento as a form error and keeps the data', function () {
    $administrador = administradorDe($this->a);
    invitar($administrador, filaParaInvitar(['correo' => 'ana.perez@example.test']));

    formularioDeInvitacion()
        ->call('invitar')
        ->assertSee('Esta persona ya tiene una invitación pendiente en este establecimiento.')
        ->assertSee('role="alert"', false)
        ->assertSet('tipoDeMensaje', 'peligro')
        ->assertSet('rut', '12.345.678-5')
        ->assertJs("document.getElementById('mensaje-invitacion').focus()");

    expect(invitacionesDe($this->a))->toBe(1);
});

it('tells the Administrador to resend when the mail could not be queued', function () {
    administradorConSesion($this->a);
    $componente = formularioDeInvitacion();
    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('cola caída con ana.perez@example.test'));

    $componente->call('invitar')
        ->assertSet('tipoDeMensaje', 'aviso')
        ->assertSee('La invitación quedó registrada, pero su correo no se pudo programar.')
        ->assertSee('Reenviarla más tarde desde las invitaciones pendientes.')
        ->assertDontSee('cola caída')
        ->assertSet('rut', '');

    expect(invitacionesDe($this->a))->toBe(1);
});

it('invites only into the active establecimiento of the session', function () {
    administradorDe($this->b);
    administradorConSesion($this->a);

    formularioDeInvitacion()->call('invitar')->assertHasNoErrors();

    expect(invitacionesDe($this->a))->toBe(1)->and(invitacionesDe($this->b))->toBe(0);
});
