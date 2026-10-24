<?php

use App\Jobs\EnviarInvitacion;
use App\Livewire\Invitaciones\InvitacionesPendientes;
use App\Models\Membresia;
use App\Support\DiasHabiles;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/../Plazos/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    // Same zone and frozen instant as Vinkured's PanelUsuariosTest: Wednesday 14-10-2026, 10:00 (America/Santiago).
    usarZonaDeVinkured();
    Model::preventLazyLoading();
    Carbon::setTestNow('2026-10-14 10:00:00');
    $this->seed(FuncionesGenericasSeeder::class);
    $this->a = newEstablecimiento('1000', 'Colegio Ficticio A');
    $this->b = newEstablecimiento('2000', 'Colegio Ficticio B');
    Queue::fake();
});

afterEach(function () {
    Carbon::setTestNow();
    Model::preventLazyLoading(false);
    restaurarZonaDelModulo();
});

/**
 * Pending invitation of $administrador's establecimiento for a fictitious person, with an optional expiry override
 * (`null` = anulada: no link).
 */
function invitacionDeLaLista(Membresia $administrador, string $nombres, string|false|null $expira = false, string $categoria = 'docente'): Membresia
{
    $slug = strtolower($nombres);
    $membresia = invitar($administrador, filaParaInvitar([
        'rut' => otroRut(),
        'nombres' => $nombres,
        'primerApellido' => 'Ficticio',
        'segundoApellido' => null,
        'correo' => "{$slug}.ficticio@example.test",
        'categoriaFuncion' => $categoria,
    ]));

    if ($expira !== false) {
        $membresia->forceFill([
            'invitacion_expira_en' => $expira,
            'invitacion_hash' => $expira === null ? null : $membresia->invitacion_hash,
        ])->save();
    }

    return $membresia->refresh();
}

function listaDeInvitaciones(): Testable
{
    app(DiasHabiles::class)->olvidar();

    return Livewire::test(InvitacionesPendientes::class);
}

/** HTML of the table row that contains $texto. */
function filaDeInvitacion(string $html, string $texto): string
{
    preg_match_all('/<tr\b[^>]*>.*?<\/tr>/s', $html, $filas);

    foreach ($filas[0] as $fila) {
        if (str_contains($fila, $texto)) {
            return $fila;
        }
    }

    throw new RuntimeException("No row contains {$texto}.");
}

it('shows the Administrador the pending invitations of this establecimiento only, with a link to invite', function () {
    invitacionDeLaLista(administradorDe($this->b), 'Bruno');
    $admin = administradorDe($this->a);
    invitacionDeLaLista($admin, 'Ines', categoria: 'asistente');

    $respuesta = $this->get(route('invitaciones.index'));

    $respuesta->assertOk()
        ->assertSee('<title>Invitaciones pendientes - VINKURED</title>', false)
        ->assertSee(route('invitaciones.create'), false)
        ->assertSee('Invitar funcionario')
        ->assertSeeInOrder(['Nombre', 'Correo', 'Función', 'Invitada el', 'Situación', 'Acciones'])
        ->assertDontSee('Bruno Ficticio')
        ->assertDontSee('bruno.ficticio@example.test');
    $fila = filaDeInvitacion($respuesta->getContent(), 'Ines Ficticio');
    expect($fila)->toContain('ines.ficticio@example.test')
        ->and($fila)->toContain('Asistente de la educación')
        ->and($fila)->toContain('14-10-2026')
        ->and(substr_count($respuesta->getContent(), '<h1'))->toBe(1)
        ->and($respuesta->getContent())->not->toContain((string) Membresia::query()->where('estado', 'invitada')->value('invitacion_hash'));
});

it('lists only invited memberships, not active or suspended ones', function () {
    $admin = administradorDe($this->a);
    invitacionDeLaLista($admin, 'Ines');
    invitacionDeLaLista($admin, 'Sergio')->forceFill(['estado' => 'suspendida'])->save();
    invitacionDeLaLista($admin, 'Alicia')->forceFill(['estado' => 'activa', 'invitacion_hash' => null, 'invitacion_expira_en' => null])->save();

    listaDeInvitaciones()
        ->assertSee('Ines Ficticio')
        ->assertDontSee('Sergio Ficticio')
        ->assertDontSee('Alicia Ficticio')
        ->assertSee('Invitaciones por activar (1)');
});

it('is forbidden to members who are not the Administrador', function () {
    [$usuario] = usuarioConRoles($this->a);
    activar($usuario, $this->a);

    $this->get(route('invitaciones.index'))->assertForbidden();
    Livewire::test(InvitacionesPendientes::class)->assertForbidden();
});

it('sends a guest to the login', function () {
    $this->get(route('invitaciones.index'))->assertRedirect(route('login'));
});

it('links the invite form back to the pending invitations', function () {
    administradorDe($this->a);

    $this->get(route('invitaciones.create'))->assertOk()->assertSee(route('invitaciones.index'), false);
});

it('offers the bulk upload as a secondary action on the pending invitations and the invite form', function (string $ruta) {
    administradorDe($this->a);

    $html = $this->get(route($ruta))->assertOk()->getContent();

    expect($html)->toMatch('/<a[^>]*href="'.preg_quote(route('importaciones.index'), '/').'"[^>]*>\\s*Carga masiva\\s*<\/a>/');
})->with(['invitaciones.index', 'invitaciones.create']);

it('marks each invitation with the host deadline badge, counting business days, and cancelled ones as Anulada', function () {
    $admin = administradorDe($this->a);
    diaNoHabil(null, '2026-10-16'); // Friday holiday
    invitacionDeLaLista($admin, 'Vencida', '2026-10-12 10:00:00');
    invitacionDeLaLista($admin, 'Hoy', '2026-10-14 18:00:00');
    invitacionDeLaLista($admin, 'Pronto', '2026-10-19 10:00:00'); // Thu 15 and Mon 19 = 2
    invitacionDeLaLista($admin, 'Manana', '2026-10-15 10:00:00');
    invitacionDeLaLista($admin, 'Anulada', null);

    $html = listaDeInvitaciones()->html();

    expect(filaDeInvitacion($html, 'Vencida Ficticio'))->toMatch('/<span[^>]*\bbg-peligro-fondo\b[^>]*>Vencida<\/span>/')
        ->and(filaDeInvitacion($html, 'Hoy Ficticio'))->toMatch('/<span[^>]*\bbg-aviso-fondo\b[^>]*>Vence hoy<\/span>/')
        ->and(filaDeInvitacion($html, 'Pronto Ficticio'))->toMatch('/<span[^>]*\bbg-neutro-fondo\b[^>]*>En plazo \(2 días hábiles\)<\/span>/')
        ->and(filaDeInvitacion($html, 'Manana Ficticio'))->toContain('En plazo (1 día hábil)')
        ->and(filaDeInvitacion($html, 'Anulada Ficticio'))->toMatch('/<span[^>]*\bbg-neutro-fondo\b[^>]*>Anulada<\/span>/');
});

it('shows the brief\'s Plazo no disponible badge when the deadline cannot be computed', function () {
    invitacionDeLaLista(administradorDe($this->a), 'Lejana', '2050-01-01 10:00:00'); // beyond the plausible range

    expect(filaDeInvitacion(listaDeInvitaciones()->html(), 'Lejana Ficticio'))
        ->toMatch('/<span[^>]*\\bbg-neutro-fondo\\b[^>]*>Plazo no disponible<\\/span>/');
});

it('says an invitation that expired earlier today expired today', function () {
    invitacionDeLaLista(administradorDe($this->a), 'Temprano', '2026-10-14 08:00:00');

    expect(filaDeInvitacion(listaDeInvitaciones()->html(), 'Temprano Ficticio'))
        ->toMatch('/<span[^>]*\bbg-peligro-fondo\b[^>]*>Vencida hoy<\/span>/');
});

it('orders expired invitations first, then by expiry, and cancelled ones last', function () {
    $admin = administradorDe($this->a);
    invitacionDeLaLista($admin, 'Anulada', null);
    invitacionDeLaLista($admin, 'Tarde', '2026-10-30 10:00:00');
    invitacionDeLaLista($admin, 'Pronto', '2026-10-16 10:00:00');
    invitacionDeLaLista($admin, 'Vieja', '2026-10-01 10:00:00');
    invitacionDeLaLista($admin, 'Reciente', '2026-10-13 10:00:00');

    listaDeInvitaciones()->assertSeeInOrder([
        'Vieja Ficticio', 'Reciente Ficticio', 'Pronto Ficticio', 'Tarde Ficticio', 'Anulada Ficticio',
    ]);
});

it('shows 20 invitations per page with the host pagination', function () {
    $admin = administradorDe($this->a);
    foreach (range(1, 23) as $n) {
        invitacionDeLaLista($admin, 'Persona'.str_pad((string) $n, 2, '0', STR_PAD_LEFT), Carbon::parse('2026-10-20 10:00:00')->addDays($n)->toDateTimeString());
    }

    $componente = listaDeInvitaciones()
        ->assertSee('Invitaciones por activar (23)')
        ->assertSee('Página 1 de 2')
        ->assertSee('Persona20 Ficticio')
        ->assertDontSee('Persona21 Ficticio');

    $componente->call('nextPage')
        ->assertSee('Página 2 de 2')
        ->assertSee('Persona21 Ficticio')
        ->assertSee('Persona23 Ficticio')
        ->assertDontSee('Persona20 Ficticio');
});

it('clamps a page beyond the last one to the last page', function () {
    invitacionDeLaLista(administradorDe($this->a), 'Unica');

    listaDeInvitaciones()->call('gotoPage', 9)->assertSee('Página 1 de 1')->assertSee('Unica Ficticio');
});

it('shows one sentence and one action when there are no pending invitations', function () {
    administradorDe($this->a);

    listaDeInvitaciones()
        ->assertSee('No hay invitaciones pendientes.')
        ->assertSee(route('invitaciones.create'), false)
        ->assertDontSee('<table', false);
});

it('resends an invitation: the old link dies, the expiry is renewed and the mail is queued', function (string|false|null $expira) {
    $invitacion = invitacionDeLaLista(administradorDe($this->a), 'Ines', $expira);
    $hashAnterior = $invitacion->invitacion_hash;
    Queue::fake();

    listaDeInvitaciones()
        ->call('reenviar', $invitacion->id)
        ->assertSet('tipoDeMensaje', 'exito')
        ->assertSee('Invitación reenviada a Ines Ficticio.')
        ->assertSeeHtml('role="status"');

    $invitacion->refresh();
    expect($invitacion->invitacion_hash)->not->toBeNull()->not->toBe($hashAnterior)
        ->and($invitacion->invitacion_expira_en->greaterThan(now()))->toBeTrue()
        ->and($invitacion->estado)->toBe('invitada');
    Queue::assertPushed(EnviarInvitacion::class, 1);
})->with([
    'vigente' => [false],
    'vencida' => ['2026-10-01 10:00:00'],
    'anulada' => [null],
]);

it('announces the action message once: its own role and focus, with no wrapping live region', function () {
    $invitacion = invitacionDeLaLista(administradorDe($this->a), 'Ines');

    $html = listaDeInvitaciones()
        ->call('reenviar', $invitacion->id)
        ->assertJs("setTimeout(() => document.getElementById('mensaje-invitaciones')?.focus(), 0)")
        ->html();

    expect($html)->not->toContain('aria-live')
        ->and(substr_count($html, 'role="status"'))->toBe(1)
        ->and($html)->toMatch('/<div[^>]*id="mensaje-invitaciones"[^>]*role="status"/');
});

it('keeps the total in the focusable heading only, not in a live region', function () {
    invitacionDeLaLista(administradorDe($this->a), 'Unica');

    $html = listaDeInvitaciones()->html();

    expect($html)->toMatch('/<h2[^>]*id="titulo-invitaciones"[^>]*tabindex="-1"[^>]*>Invitaciones por activar \\(1\\)<\\/h2>/')
        ->and($html)->not->toContain('role="status"')
        ->and($html)->not->toContain('aria-live');
});

it('explains once above the table what Reenviar does and ties the hint to every Reenviar button', function () {
    $admin = administradorDe($this->a);
    invitacionDeLaLista($admin, 'Ines');
    invitacionDeLaLista($admin, 'Olga');

    $html = listaDeInvitaciones()->html();

    expect(substr_count($html, 'Se enviará un nuevo enlace; el anterior dejará de funcionar.'))->toBe(1)
        ->and($html)->toMatch('/<p[^>]*id="aviso-reenviar"[^>]*>Se enviará un nuevo enlace; el anterior dejará de funcionar\\.<\\/p>/')
        ->and(filaDeInvitacion($html, 'Ines Ficticio'))->toMatch('/<button[^>]*aria-describedby="aviso-reenviar"[^>]*>\\s*<span[^>]*>Reenviar/')
        ->and(filaDeInvitacion($html, 'Olga Ficticio'))->toMatch('/<button[^>]*aria-describedby="aviso-reenviar"[^>]*>\\s*<span[^>]*>Reenviar/');
});

it('warns with retry guidance when the resent mail cannot be queued', function () {
    $invitacion = invitacionDeLaLista(administradorDe($this->a), 'Ines');
    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('cola caída con ines.ficticio@example.test'));

    listaDeInvitaciones()
        ->call('reenviar', $invitacion->id)
        ->assertSet('tipoDeMensaje', 'aviso')
        ->assertSee('El enlace anterior de Ines Ficticio quedó sin efecto, pero el correo nuevo no se pudo programar. Reenviar la invitación en unos minutos.')
        ->assertSeeHtml('role="alert"')
        ->assertDontSee('cola caída');
});

it('asks for confirmation before cancelling and then kills the link', function () {
    $invitacion = invitacionDeLaLista(administradorDe($this->a), 'Ines');

    $componente = listaDeInvitaciones()
        ->call('anular')
        ->assertSet('anulando', null);
    expect($invitacion->refresh()->invitacion_hash)->not->toBeNull();

    $componente->call('confirmarAnulacion', $invitacion->id)
        ->assertSet('anulando', $invitacion->id)
        ->assertSee('¿Anular la invitación de Ines Ficticio?')
        ->call('cancelar')
        ->assertSet('anulando', null);
    expect($invitacion->refresh()->invitacion_hash)->not->toBeNull();

    $componente->call('confirmarAnulacion', $invitacion->id)
        ->call('anular')
        ->assertSet('anulando', null)
        ->assertSet('tipoDeMensaje', 'exito')
        ->assertSee('Invitación de Ines Ficticio anulada. Su enlace ya no sirve.');

    $invitacion->refresh();
    expect($invitacion->invitacion_hash)->toBeNull()
        ->and($invitacion->invitacion_expira_en)->toBeNull()
        ->and($invitacion->estado)->toBe('invitada');
    $fila = filaDeInvitacion($componente->html(), 'Ines Ficticio');
    expect($fila)->toMatch('/<span[^>]*\bbg-neutro-fondo\b[^>]*>Anulada<\/span>/')
        ->and($fila)->not->toContain('confirmarAnulacion')
        ->and($fila)->toContain('reenviar(');
});

it('offers Anular only while the invitation has a live link', function () {
    $admin = administradorDe($this->a);
    invitacionDeLaLista($admin, 'Vigente');
    invitacionDeLaLista($admin, 'Anulada', null);

    $html = listaDeInvitaciones()->html();

    expect(filaDeInvitacion($html, 'Vigente Ficticio'))->toContain('confirmarAnulacion(')
        ->and(filaDeInvitacion($html, 'Anulada Ficticio'))->not->toContain('confirmarAnulacion(');
});

it('rejects a tampered id from another establecimiento and changes nothing', function (string $accion) {
    $ajena = invitacionDeLaLista(administradorDe($this->b), 'Bruno');
    administradorDe($this->a);
    Queue::fake();

    listaDeInvitaciones()->call($accion, $ajena->id)->assertNotFound();

    $despues = Membresia::query()->findOrFail($ajena->id);
    expect($despues->invitacion_hash)->toBe($ajena->invitacion_hash)
        ->and($despues->invitacion_expira_en->equalTo($ajena->invitacion_expira_en))->toBeTrue();
    Queue::assertNothingPushed();
})->with(['reenviar', 'confirmarAnulacion']);

it('rejects a malformed id', function () {
    administradorDe($this->a);

    listaDeInvitaciones()->call('reenviar', 'abc')->assertNotFound();
});

it('re-checks the Administrador on every action', function () {
    $admin = administradorDe($this->a);
    $invitacion = invitacionDeLaLista($admin, 'Ines');
    $confirmando = listaDeInvitaciones()->call('confirmarAnulacion', $invitacion->id);
    $abierta = listaDeInvitaciones();
    $admin->forceFill(['es_administrador' => false])->save();
    Queue::fake();

    $confirmando->call('anular')->assertForbidden();
    $abierta->call('reenviar', $invitacion->id)->assertForbidden();

    expect($invitacion->refresh()->invitacion_hash)->not->toBeNull();
    Queue::assertNothingPushed();
});

it('warns when the invitation is no longer pending', function () {
    $invitacion = invitacionDeLaLista(administradorDe($this->a), 'Ines');
    $componente = listaDeInvitaciones();
    Queue::fake();
    $invitacion->forceFill(['estado' => 'activa', 'invitacion_hash' => null, 'invitacion_expira_en' => null])->save();

    $componente->call('reenviar', $invitacion->id)
        ->assertSet('tipoDeMensaje', 'aviso')
        ->assertSee('Esta persona ya no tiene una invitación pendiente en este establecimiento.');
    Queue::assertNothingPushed();
});

it('orders and labels invitations by local time around midnight in America/Santiago', function () {
    // 23:59:30 local = 02:59:30 UTC the next day: a UTC comparison would call the 00:00:30 invitation expired.
    Carbon::setTestNow('2026-10-14 23:59:30');
    $admin = administradorDe($this->a);
    invitacionDeLaLista($admin, 'Anulada', null);
    invitacionDeLaLista($admin, 'Manana', '2026-10-15 00:00:30');
    invitacionDeLaLista($admin, 'Antes', '2026-10-14 23:59:00');

    $html = listaDeInvitaciones()
        ->assertSeeInOrder(['Antes Ficticio', 'Manana Ficticio', 'Anulada Ficticio'])
        ->html();

    expect(filaDeInvitacion($html, 'Antes Ficticio'))->toMatch('/<span[^>]*\bbg-peligro-fondo\b[^>]*>Vencida hoy<\/span>/')
        ->and(filaDeInvitacion($html, 'Manana Ficticio'))->not->toContain('Vencida')
        ->and(filaDeInvitacion($html, 'Manana Ficticio'))->toMatch('/<span[^>]*\bbg-neutro-fondo\b[^>]*>En plazo \(1 día hábil\)<\/span>/');
});

it('orders and labels invitations by local time right after the September DST change', function () {
    // 06-09-2026: local clocks jump from 00:00 to 01:00 (UTC-4 -> UTC-3).
    Carbon::setTestNow('2026-09-06 01:30:00');
    $admin = administradorDe($this->a);
    invitacionDeLaLista($admin, 'Luego', '2026-09-06 02:00:00');
    invitacionDeLaLista($admin, 'Sabado', '2026-09-05 23:45:00');

    $html = listaDeInvitaciones()
        ->assertSeeInOrder(['Sabado Ficticio', 'Luego Ficticio'])
        ->html();

    expect(filaDeInvitacion($html, 'Sabado Ficticio'))->toMatch('/<span[^>]*\bbg-peligro-fondo\b[^>]*>Vencida<\/span>/')
        ->and(filaDeInvitacion($html, 'Luego Ficticio'))->toMatch('/<span[^>]*\bbg-aviso-fondo\b[^>]*>Vence hoy<\/span>/');
});

it('re-checks under the action that the link is still live: cancelled in between warns and changes nothing', function () {
    $invitacion = invitacionDeLaLista(administradorDe($this->a), 'Ines');
    $componente = listaDeInvitaciones()->call('confirmarAnulacion', $invitacion->id);
    $invitacion->forceFill(['invitacion_hash' => null, 'invitacion_expira_en' => null])->save();
    $antes = $invitacion->refresh()->updated_at;

    $componente->call('anular')
        ->assertSet('anulando', null)
        ->assertSet('tipoDeMensaje', 'aviso')
        ->assertSee('Esta invitación ya no tiene un enlace que anular.')
        ->assertDontSee('anulada. Su enlace ya no sirve.');

    expect($invitacion->refresh()->estado)->toBe('invitada')
        ->and($invitacion->updated_at->equalTo($antes))->toBeTrue();
});

it('re-checks under the action that the invitation is still pending: accepted in between warns and changes nothing', function () {
    $invitacion = invitacionDeLaLista(administradorDe($this->a), 'Ines');
    $componente = listaDeInvitaciones()->call('confirmarAnulacion', $invitacion->id);
    $invitacion->forceFill(['estado' => 'activa', 'invitacion_hash' => null, 'invitacion_expira_en' => null])->save();

    $componente->call('anular')
        ->assertSet('anulando', null)
        ->assertSet('tipoDeMensaje', 'aviso')
        ->assertSee('Esta invitación ya no tiene un enlace que anular.');

    expect($invitacion->refresh()->estado)->toBe('activa');
});
