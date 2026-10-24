<?php

/*
 * Ported from Vinkured tests/Feature/Acceso/LoginTest.php and LogoutTest.php (cases that apply to the module's
 * copy of the host Login, SeleccionarEstablecimiento and access routes). Fictitious data only.
 */

use App\Livewire\Login;
use App\Livewire\SeleccionarEstablecimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    RateLimiter::clear('ana@example.test|127.0.0.1');
});

function intentarLogin(string $correo, string $password = 'password'): Testable
{
    return Livewire::test(Login::class)
        ->set('correo', $correo)
        ->set('contrasena', $password)
        ->call('iniciarSesion');
}

it('renders the login form with labelled fields and the mandatory marker', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Iniciar sesión')
        ->assertSee('Correo (obligatorio)')
        ->assertSee('Contraseña (obligatorio)')
        ->assertDontSee('Recordarme')
        ->assertDontSee('remember', false);
});

it('never submits credentials with GET when JavaScript is unavailable', function () {
    expect($this->get('/login')->getContent())->toMatch('/<form\b[^>]*method="post"/i');
});

it('lets a user with exactly one active membership in and sets it as active', function () {
    $est = newEstablecimiento('2001');
    $u = usuarioConMembresia($est, 'activa', ['correo' => 'ana@example.test']);

    intentarLogin('ana@example.test')->assertRedirect(route('panel'));

    $this->assertAuthenticatedAs($u);
    expect(session('establecimiento_id'))->toBe($est);
});

it('sends a user with several active memberships to the selector without choosing one', function () {
    $a = newEstablecimiento('2001');
    $b = newEstablecimiento('2002');
    $u = usuarioConMembresia($a, 'activa', ['correo' => 'ana@example.test']);
    newMembresia($b, $u->id, null, ['estado' => 'activa']);

    intentarLogin('ana@example.test')->assertRedirect(route('establecimientos.seleccionar'));

    $this->assertAuthenticatedAs($u);
    expect(session()->has('establecimiento_id'))->toBeFalse();
});

it('trims and lowercases the email before authenticating', function () {
    usuarioConMembresia(newEstablecimiento('2001'), 'activa', ['correo' => 'ana@example.test']);

    intentarLogin('  ANA@Example.Test ')->assertRedirect(route('panel'));
});

it('denies access without an active membership and logs the user out', function (string $estado) {
    usuarioConMembresia(newEstablecimiento('2001'), $estado, ['correo' => 'ana@example.test']);

    intentarLogin('ana@example.test')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session()->has('establecimiento_id'))->toBeFalse()
        ->and(session('aviso'))->toContain('Contactar al Administrador del establecimiento');
})->with(['invitada', 'suspendida']);

it('ignores non-active memberships when choosing where to send the user', function () {
    $a = newEstablecimiento('2001');
    $b = newEstablecimiento('2002');
    $u = usuarioConMembresia($a, 'activa', ['correo' => 'ana@example.test']);
    newMembresia($b, $u->id, null, ['estado' => 'invitada']);

    intentarLogin('ana@example.test')->assertRedirect(route('panel'));

    expect(session('establecimiento_id'))->toBe($a);
});

it('gives the same generic error for an unknown email and a wrong password', function () {
    usuarioConMembresia(newEstablecimiento('2001'), 'activa', ['correo' => 'ana@example.test']);

    $desconocido = intentarLogin('nadie@example.test', 'otra-clave')->assertHasErrors('credenciales');
    $incorrecta = intentarLogin('ana@example.test', 'otra-clave')->assertHasErrors('credenciales');

    expect($desconocido->errors()->get('credenciales'))->toBe($incorrecta->errors()->get('credenciales'))
        ->and($incorrecta->errors()->get('credenciales'))->toBe(['Las credenciales no coinciden.']);
    $this->assertGuest();
});

it('changes the session id on login to prevent fixation', function () {
    usuarioConMembresia(newEstablecimiento('2001'), 'activa', ['correo' => 'ana@example.test']);
    $antes = session()->getId();

    intentarLogin('ana@example.test')->assertRedirect(route('panel'));

    expect(session()->getId())->not->toBe($antes);
});

it('redirects authenticated users away from the login page', function () {
    $est = newEstablecimiento('2001');

    $this->actingAs(usuarioConMembresia($est))->get('/login')->assertRedirect(route('panel'));
});

it('redirects a guest from a module route to the login', function () {
    $this->get('/panel')->assertRedirect(route('login'));
    $this->get('/establecimientos/seleccionar')->assertRedirect(route('login'));
});

it('lands an active user with one establecimiento on the module home', function () {
    $est = newEstablecimiento('2001', 'Colegio Ficticio Uno');
    $u = usuarioConMembresia($est, 'activa', ['correo' => 'ana@example.test']);
    intentarLogin('ana@example.test')->assertRedirect(route('panel'));

    $this->actingAs($u)->get('/panel')
        ->assertOk()
        ->assertSee('Acompañamiento docente')
        ->assertSee('Cerrar sesión');
});

it('sends a logged-in user without an active establecimiento from the home to the selection', function () {
    $a = newEstablecimiento('2001');
    $u = usuarioConMembresia($a);
    newMembresia(newEstablecimiento('2002'), $u->id, null, ['estado' => 'activa']);

    $this->actingAs($u)->get('/panel')->assertRedirect(route('establecimientos.seleccionar'));
});

it('lists only active memberships on the selection page and enters the chosen one', function () {
    $a = newEstablecimiento('2001', 'Colegio Ficticio A');
    $b = newEstablecimiento('2002', 'Colegio Ficticio B');
    $c = newEstablecimiento('2003', 'Colegio Ficticio C');
    $u = usuarioConMembresia($a);
    newMembresia($b, $u->id, null, ['estado' => 'activa']);
    newMembresia($c, $u->id, null, ['estado' => 'invitada']);

    $this->actingAs($u)->get('/establecimientos/seleccionar')
        ->assertOk()
        ->assertSee('Colegio Ficticio A')
        ->assertSee('Colegio Ficticio B')
        ->assertDontSee('Colegio Ficticio C');

    Livewire::actingAs($u)->test(SeleccionarEstablecimiento::class)
        ->set('seleccionado', (string) $b)
        ->call('entrar')
        ->assertRedirect(route('panel'));
    expect(session('establecimiento_id'))->toBe($b);
});

it('refuses to enter an establecimiento where the membership is only invited', function () {
    $a = newEstablecimiento('2001');
    $u = usuarioConMembresia($a);
    newMembresia(newEstablecimiento('2002'), $u->id, null, ['estado' => 'activa']);
    $invitado = newEstablecimiento('2003');
    newMembresia($invitado, $u->id, null, ['estado' => 'invitada']);

    Livewire::actingAs($u)->test(SeleccionarEstablecimiento::class)
        ->set('seleccionado', (string) $invitado)
        ->call('entrar')
        ->assertForbidden();
});

it('logs out, invalidates the session and redirects to the login page', function () {
    $est = newEstablecimiento('4001');
    $u = usuarioConMembresia($est);
    $this->actingAs($u)->withSession(['establecimiento_id' => $est, 'otro' => 'dato']);
    $antes = session()->getId();

    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session()->has('establecimiento_id'))->toBeFalse()
        ->and(session()->has('otro'))->toBeFalse()
        ->and(session()->getId())->not->toBe($antes);
});

it('does not log out with GET', function () {
    $u = usuarioConMembresia(newEstablecimiento('4001'));

    $this->actingAs($u)->get('/logout')->assertStatus(405);
    $this->assertAuthenticatedAs($u);
});

/*
 * Lockout cases ported from Vinkured tests/Feature/Acceso/LoginTest.php ("locks the sixth attempt, even with the
 * right password, and reports the remaining seconds", "pluralizes the remaining lockout seconds", "does not lock a
 * different email from the same ip").
 */

it('locks the sixth attempt, even with the right password, and reports the remaining seconds', function () {
    $est = newEstablecimiento('2001');
    usuarioConMembresia($est, 'activa', ['correo' => 'ana@example.test']);

    foreach (range(1, 5) as $_) {
        intentarLogin('ana@example.test', 'mal')->assertHasErrors('credenciales');
    }

    $bloqueado = intentarLogin('ana@example.test', 'password')->assertHasErrors('bloqueo');

    $this->assertGuest();
    expect($bloqueado->errors()->has('correo'))->toBeFalse()
        ->and($bloqueado->errors()->first('bloqueo'))->toMatch('/^Demasiados intentos\. .* \d+ segundos?\.$/');
    $bloqueado->assertJs("document.getElementById('alerta-login').focus()");
});

it('pluralizes the remaining lockout seconds', function (int $transcurridos, string $esperado) {
    $est = newEstablecimiento('2001');
    usuarioConMembresia($est, 'activa', ['correo' => 'ana@example.test']);
    $this->freezeTime();
    foreach (range(1, 5) as $_) {
        intentarLogin('ana@example.test', 'mal');
    }

    $this->travel($transcurridos)->seconds();

    expect(intentarLogin('ana@example.test', 'password')->errors()->first('bloqueo'))->toEndWith(' '.$esperado.'.');
})->with([[59, '1 segundo'], [58, '2 segundos'], [30, '30 segundos']]);

it('does not lock a different email from the same ip', function () {
    $est = newEstablecimiento('2001');
    usuarioConMembresia($est, 'activa', ['correo' => 'ana@example.test']);
    $otro = usuarioConMembresia($est, 'activa', ['correo' => 'beto@example.test']);
    foreach (range(1, 5) as $_) {
        intentarLogin('ana@example.test', 'mal');
    }

    intentarLogin('beto@example.test')->assertHasNoErrors()->assertRedirect(route('panel'));

    $this->assertAuthenticatedAs($otro);
    RateLimiter::clear('beto@example.test|127.0.0.1');
});
