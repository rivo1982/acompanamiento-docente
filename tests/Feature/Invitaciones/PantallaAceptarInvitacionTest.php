<?php

use App\Livewire\Invitaciones\AceptarInvitacion;
use App\Models\Membresia;
use App\Models\Usuario;
use App\Support\Invitaciones\EnlaceDeInvitacion;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

const CLAVE_DE_PANTALLA = 'ClaveSegura2026';

const MENSAJE_DE_ENLACE_INVALIDO = 'La invitación no es válida o ya venció.';

beforeEach(function () {
    $this->withoutVite();
    $this->seed(FuncionesGenericasSeeder::class);
    $this->a = newEstablecimiento('1000', 'Colegio Ficticio A');
    $this->b = newEstablecimiento('2000', 'Colegio Ficticio B');
    Mail::fake();
    RateLimiter::clear('invitaciones:127.0.0.1');
});

/** Invites a person into Colegio A (as its Administrador), then leaves no session. Returns the invited membresia. */
function invitacionPendiente(int $estId, array $fila = []): Membresia
{
    $membresia = invitar(administradorDe($estId), filaParaInvitar($fila));
    sinContexto();

    return $membresia;
}

function pantalla(string $token, ?Usuario $como = null): Testable
{
    return $como
        ? Livewire::actingAs($como)->test(AceptarInvitacion::class, ['token' => $token])
        : Livewire::test(AceptarInvitacion::class, ['token' => $token]);
}

/** Visible text of a page, without tags, scripts or attributes. */
function textoVisible(TestResponse|string $pagina): string
{
    $html = is_string($pagina) ? $pagina : $pagina->getContent();

    return strip_tags((string) preg_replace('~<(script|style)\b.*?</\1>~is', '', $html));
}

it('builds the invitation link with the named route', function () {
    expect(app(EnlaceDeInvitacion::class)->para('abc'))->toBe(route('invitaciones.aceptar', 'abc'))
        ->and(route('invitaciones.aceptar', 'abc'))->toBe(url('/invitaciones/abc'));
});

it('shows a new account the password form with the rule before submitting, without the token, correo or RUT', function () {
    invitacionPendiente($this->a, ['correo' => 'nueva.persona@example.test']);
    $token = tokenDeLaUltimaInvitacion();

    $respuesta = $this->get(route('invitaciones.aceptar', $token));

    $respuesta->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('<title>Aceptar invitación - VINKURED</title>', false)
        ->assertSee('Colegio Ficticio A')
        ->assertSee('Contraseña nueva (obligatorio)')
        ->assertSee('Confirmar contraseña (obligatorio)')
        ->assertSee('Mínimo 12 caracteres, con letras y números.')
        ->assertDontSee('nueva.persona@example.test')
        ->assertDontSee(rutDeInvitado(12345678)->formateado());
    $html = $respuesta->getContent();
    expect(substr_count($html, '<h1'))->toBe(1)
        ->and(substr_count($html, 'autocomplete="new-password"'))->toBe(2)
        ->and($html)->toMatch('/<form\b[^>]*method="post"[^>]*wire:submit="aceptar"/')
        ->and($html)->toContain('aria-describedby="contrasena-ayuda"')
        ->and(textoVisible($html))->not->toContain($token)
        // The only copy in the HTML is the page URL that Livewire keeps in its memo (`path`); the data never has it.
        ->and(substr_count($html, $token))->toBe(1)
        ->and($html)->toContain('&quot;path&quot;:&quot;invitaciones\\/'.$token.'&quot;');
    expect(json_encode(Livewire::test(AceptarInvitacion::class, ['token' => $token])->snapshot['data']))->not->toContain($token);
});

it('activates a new account with the chosen password and shows the success state', function () {
    $membresia = invitacionPendiente($this->a);

    pantalla(tokenDeLaUltimaInvitacion())
        ->set('contrasena', CLAVE_DE_PANTALLA)
        ->set('contrasena_confirmation', CLAVE_DE_PANTALLA)
        ->call('aceptar')
        ->assertHasNoErrors()
        ->assertSet('estado', AceptarInvitacion::ACEPTADA)
        ->assertSet('contrasena', '')
        ->assertSet('contrasena_confirmation', '')
        ->assertSee('Invitación aceptada.')
        ->assertSee(route('login'))
        ->assertJs("document.getElementById('estado-invitacion').focus()");

    $membresia->refresh();
    expect($membresia->estado)->toBe('activa')
        ->and(Hash::check(CLAVE_DE_PANTALLA, Usuario::query()->findOrFail($membresia->usuario_id)->password))->toBeTrue();
    $this->assertGuest();
});

it('rejects a password that breaks the rule or does not match, without consuming the link', function (string $clave, string $confirmacion, string $campo, string $mensaje) {
    $membresia = invitacionPendiente($this->a);
    $token = tokenDeLaUltimaInvitacion();

    pantalla($token)
        ->set('contrasena', $clave)
        ->set('contrasena_confirmation', $confirmacion)
        ->call('aceptar')
        ->assertHasErrors($campo)
        ->assertSee($mensaje)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="contrasena-ayuda contrasena-error"', false)
        ->assertSet('estado', AceptarInvitacion::NUEVA_CONTRASENA)
        ->assertSet('contrasena', '')
        ->assertJs("document.getElementById('contrasena').focus()");

    expect($membresia->fresh()->estado)->toBe('invitada');

    pantalla($token)
        ->set('contrasena', CLAVE_DE_PANTALLA)
        ->set('contrasena_confirmation', CLAVE_DE_PANTALLA)
        ->call('aceptar')
        ->assertSet('estado', AceptarInvitacion::ACEPTADA);
})->with([
    'corta' => ['Clave2026', 'Clave2026', 'contrasena', 'La contraseña debe tener al menos 12 caracteres.'],
    'sin números' => ['SoloLetrasLargas', 'SoloLetrasLargas', 'contrasena', 'La contraseña debe tener al menos un número.'],
    'sin letras' => ['123456789012', '123456789012', 'contrasena', 'La contraseña debe tener al menos una letra.'],
    'vacía' => ['', '', 'contrasena', 'La contraseña es obligatoria.'],
    'distinta' => [CLAVE_DE_PANTALLA, 'OtraClave2026', 'contrasena', 'Las contraseñas no coinciden.'],
]);

it('shows one generic message and no form for every unusable link', function (Closure $preparar) {
    $token = $preparar($this);

    $respuesta = $this->get(route('invitaciones.aceptar', $token));

    $respuesta->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee(MENSAJE_DE_ENLACE_INVALIDO)
        ->assertSee('Pedir una nueva invitación al Administrador del establecimiento.')
        ->assertDontSee('Colegio Ficticio A')
        ->assertDontSee('Contraseña nueva')
        ->assertDontSee('wire:submit', false);
    expect(textoVisible($respuesta))->not->toContain($token);
})->with([
    'desconocido' => [fn ($t): string => str_repeat('a', 43)],
    'malformado' => [fn ($t): string => 'no-es-un-token'],
    'vencido' => [function ($t): string {
        invitacionPendiente($t->a);
        $token = tokenDeLaUltimaInvitacion();
        $t->travel(8)->days();

        return $token;
    }],
    'usado' => [function ($t): string {
        invitacionPendiente($t->a);
        $token = tokenDeLaUltimaInvitacion();
        app(App\Actions\AceptarInvitacion::class)->aceptar($token, CLAVE_DE_PANTALLA, null);

        return $token;
    }],
    'anulado' => [function ($t): string {
        $membresia = invitacionPendiente($t->a);
        $token = tokenDeLaUltimaInvitacion();
        DB::table('membresias')->where('id', $membresia->id)->update(['invitacion_hash' => null, 'invitacion_expira_en' => null]);

        return $token;
    }],
    'reemplazado por un reenvío' => [function ($t): string {
        $membresia = invitacionPendiente($t->a);
        $token = tokenDeLaUltimaInvitacion();
        DB::table('membresias')->where('id', $membresia->id)->update(['invitacion_hash' => hash('sha256', 'otro')]);

        return $token;
    }],
]);

it('falls back to the generic message when the link stops working before the submit', function () {
    $membresia = invitacionPendiente($this->a);
    $componente = pantalla(tokenDeLaUltimaInvitacion());
    DB::table('membresias')->where('id', $membresia->id)->update(['invitacion_expira_en' => now()->subMinute()]);

    $componente->set('contrasena', CLAVE_DE_PANTALLA)
        ->set('contrasena_confirmation', CLAVE_DE_PANTALLA)
        ->call('aceptar')
        ->assertSet('estado', AceptarInvitacion::INVALIDA)
        ->assertSee(MENSAJE_DE_ENLACE_INVALIDO)
        ->assertDontSee('Colegio Ficticio A');

    expect($membresia->fresh()->estado)->toBe('invitada');
});

it('asks a guest with an established account to log in, without a form or its other establecimientos', function () {
    $existente = usuarioConMembresia($this->b, 'activa', ['correo' => 'ya.existe@example.test']);
    invitacionPendiente($this->a, ['correo' => 'ya.existe@example.test']);
    $token = tokenDeLaUltimaInvitacion();

    $this->get(route('invitaciones.aceptar', $token))
        ->assertOk()
        ->assertSee('Esta invitación es para una cuenta que ya existe.')
        ->assertSee('Iniciar sesión con esa cuenta y volver a abrir el enlace de la invitación.')
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('Colegio Ficticio A')
        ->assertDontSee('Colegio Ficticio B')
        ->assertDontSee('ya.existe@example.test')
        ->assertDontSee('Contraseña nueva');

    expect(Hash::check('password', $existente->fresh()->password))->toBeTrue();
});

it('lets the invited user accept with one button when logged in, then enters the new establecimiento', function () {
    $existente = usuarioConMembresia($this->b, 'activa', ['correo' => 'ya.existe@example.test']);
    $membresia = invitacionPendiente($this->a, ['correo' => 'ya.existe@example.test']);
    $token = tokenDeLaUltimaInvitacion();

    $this->actingAs($existente)->get(route('invitaciones.aceptar', $token))
        ->assertOk()
        ->assertSee('Colegio Ficticio A')
        ->assertDontSee('Colegio Ficticio B')
        ->assertDontSee('Contraseña nueva');

    pantalla($token, $existente)
        ->assertSet('estado', AceptarInvitacion::CONFIRMAR)
        ->call('aceptar')
        ->assertRedirect(route('panel'));

    expect($membresia->fresh()->estado)->toBe('activa')
        ->and(session('establecimiento_id'))->toBe($this->a)
        ->and(Hash::check('password', $existente->fresh()->password))->toBeTrue();
});

it('asks a different logged-in user to log out first, without data about the invitation', function (bool $cuentaExistente) {
    if ($cuentaExistente) {
        usuarioConMembresia($this->b, 'activa', ['correo' => 'ya.existe@example.test']);
    }
    $membresia = invitacionPendiente($this->a, ['correo' => 'ya.existe@example.test']);
    $token = tokenDeLaUltimaInvitacion();
    $otro = usuarioConMembresia($this->b);

    $this->actingAs($otro)->get(route('invitaciones.aceptar', $token))
        ->assertOk()
        ->assertSee('La sesión iniciada es de otra cuenta.')
        ->assertSee('Cerrar sesión y volver a abrir el enlace de la invitación.')
        ->assertDontSee('Colegio Ficticio A')
        ->assertDontSee('ya.existe@example.test')
        ->assertDontSee('Contraseña nueva');

    pantalla($token, $otro)->assertSet('estado', AceptarInvitacion::OTRA_CUENTA)->call('aceptar');

    expect($membresia->fresh()->estado)->toBe('invitada');
})->with(['cuenta nueva' => false, 'cuenta existente' => true]);

it('throttles the invitation page per ip to slow down token guessing', function () {
    foreach (range(1, 10) as $_) {
        $this->get(route('invitaciones.aceptar', str_repeat('b', 43)))->assertOk();
    }

    $this->get(route('invitaciones.aceptar', str_repeat('c', 43)))->assertTooManyRequests();
});

it('does not require an active establecimiento from a logged-in user', function () {
    $u = usuarioConMembresia($this->b);
    usuarioConMembresia($this->a, 'activa');
    newMembresia($this->a, $u->id, null, ['estado' => 'activa']);

    $this->actingAs($u)->get(route('invitaciones.aceptar', str_repeat('a', 43)))
        ->assertOk()
        ->assertSee(MENSAJE_DE_ENLACE_INVALIDO);
});
