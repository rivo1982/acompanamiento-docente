<?php

use App\Actions\AceptarInvitacion;
use App\Actions\AnularInvitacion;
use App\Actions\ReenviarInvitacion;
use App\Exceptions\InvitacionInvalida;
use App\Exceptions\InvitacionNoPendiente;
use App\Jobs\EnviarInvitacion;
use App\Models\Membresia;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FuncionesGenericasSeeder::class);
    $this->a = newEstablecimiento('1000', 'Colegio A');
    $this->b = newEstablecimiento('2000', 'Colegio B');
});

function reenviar(Membresia $administrador, int $membresiaId): void
{
    app(ReenviarInvitacion::class)->reenviar($administrador, $membresiaId);
}

function anular(Membresia $administrador, int $membresiaId): void
{
    app(AnularInvitacion::class)->anular($administrador, $membresiaId);
}

function aceptarCon(string $token): Membresia
{
    return app(AceptarInvitacion::class)->aceptar($token, 'ClaveSegura2026', null);
}

it('reenviar rota el enlace y renueva la vigencia', function () {
    Mail::fake();
    $this->freezeSecond();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);
    $tokenViejo = tokenDeLaUltimaInvitacion();

    $this->travel(3)->days();
    reenviar($administrador, $membresia->id);
    $tokenNuevo = tokenDeLaUltimaInvitacion();

    expect($tokenNuevo)->not->toBe($tokenViejo)
        ->and($membresia->fresh()->invitacion_expira_en->equalTo(now()->addDays(7)))->toBeTrue()
        ->and(fn () => aceptarCon($tokenViejo))->toThrow(InvitacionInvalida::class)
        ->and(aceptarCon($tokenNuevo)->estado)->toBe('activa');
    Mail::assertSentCount(2);
});

it('reenviar invalida el enlace anterior aunque el correo aún no salga', function () {
    Mail::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);
    $tokenViejo = tokenDeLaUltimaInvitacion();
    Queue::fake();

    reenviar($administrador, $membresia->id);

    Queue::assertPushed(EnviarInvitacion::class, 1);
    expect(fn () => aceptarCon($tokenViejo))->toThrow(InvitacionInvalida::class);
});

it('solo reenvía o anula invitaciones pendientes', function (string $accion) {
    Mail::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);
    aceptarCon(tokenDeLaUltimaInvitacion());

    expect(fn () => $accion($administrador, $membresia->id))
        ->toThrow(InvitacionNoPendiente::class, 'Esta persona ya no tiene una invitación pendiente en este establecimiento.')
        ->and($membresia->fresh()->estado)->toBe('activa');
    Mail::assertSentCount(1);
})->with(['reenviar', 'anular']);

it('anular corta el enlace, deja la membresía invitada y es idempotente', function () {
    Mail::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);
    $token = tokenDeLaUltimaInvitacion();

    anular($administrador, $membresia->id);
    anular($administrador, $membresia->id);

    $membresia->refresh();
    expect($membresia->estado)->toBe('invitada')
        ->and($membresia->invitacion_hash)->toBeNull()
        ->and($membresia->invitacion_expira_en)->toBeNull()
        ->and(fn () => aceptarCon($token))->toThrow(InvitacionInvalida::class);
});

it('el trabajo pendiente no envía una invitación anulada', function () {
    Queue::fake();
    Mail::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);

    anular($administrador, $membresia->id);
    app()->call([new EnviarInvitacion($this->a, $membresia->id), 'handle']);

    Mail::assertNothingSent();
    expect($membresia->fresh()->invitacion_hash)->toBeNull();
});

it('reenviar una invitación anulada la vuelve a enviar', function () {
    Mail::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);
    anular($administrador, $membresia->id);

    reenviar($administrador, $membresia->id);

    expect(aceptarCon(tokenDeLaUltimaInvitacion())->estado)->toBe('activa');
});

it('no reenvía ni anula invitaciones de otro establecimiento', function (string $accion) {
    Mail::fake();
    $deB = invitar(administradorDe($this->b));
    $antes = (array) DB::table('membresias')->find($deB->id);
    $administradorDeA = administradorDe($this->a);

    expect(fn () => $accion($administradorDeA, $deB->id))->toThrow(ModelNotFoundException::class)
        ->and((array) DB::table('membresias')->find($deB->id))->toBe($antes);
    Mail::assertSentCount(1);
})->with(['reenviar', 'anular']);

it('solo el Administrador reenvía o anula', function (string $accion) {
    Mail::fake();
    $membresia = invitar(administradorDe($this->a));
    $usuario = usuarioConMembresia($this->a);
    activar($usuario, $this->a);
    $sinRol = Membresia::query()->where('usuario_id', $usuario->id)->firstOrFail();

    expect(fn () => $accion($sinRol, $membresia->id))->toThrow(AuthorizationException::class)
        ->and($membresia->fresh()->invitacion_hash)->toBe($membresia->invitacion_hash);
})->with(['reenviar', 'anular']);

it('la gate invitar-funcionarios solo deja pasar al Administrador del establecimiento activo', function () {
    [$administrador] = usuarioConRoles($this->a, administrador: true);
    newMembresia($this->b, $administrador->id, null, ['estado' => 'activa']);
    [$directivo] = usuarioDelModulo($this->a, 'administrador');
    $comun = usuarioConMembresia($this->a);

    expect(puede($administrador, $this->a, 'invitar-funcionarios'))->toBeTrue()
        ->and(puede($administrador, $this->b, 'invitar-funcionarios'))->toBeFalse()
        ->and(puede($directivo, $this->a, 'invitar-funcionarios'))->toBeFalse()
        ->and(puede($comun, $this->a, 'invitar-funcionarios'))->toBeFalse();
});
