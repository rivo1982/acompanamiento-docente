<?php

use App\Actions\AceptarInvitacion;
use App\Exceptions\InvitacionInvalida;
use App\Exceptions\InvitacionRequiereSesion;
use App\Models\Membresia;
use App\Models\Usuario;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

const CLAVE_VALIDA = 'ClaveSegura2026';

beforeEach(function () {
    $this->seed(FuncionesGenericasSeeder::class);
    $this->a = newEstablecimiento('1000', 'Colegio A');
    $this->b = newEstablecimiento('2000', 'Colegio B');
    Mail::fake();
    $this->freezeSecond();
});

function aceptar(string $token, ?string $clave = CLAVE_VALIDA, ?Usuario $autenticado = null): Membresia
{
    return app(AceptarInvitacion::class)->aceptar($token, $clave, $autenticado);
}

it('activa la membresía y fija la contraseña de una cuenta nueva', function () {
    $membresia = invitar(administradorDe($this->a));

    $aceptada = aceptar(tokenDeLaUltimaInvitacion());

    $membresia->refresh();
    $usuario = Usuario::query()->findOrFail($membresia->usuario_id);
    expect($aceptada->id)->toBe($membresia->id)
        ->and($membresia->estado)->toBe('activa')
        ->and($membresia->activada_en->equalTo(now()))->toBeTrue()
        ->and($membresia->invitacion_hash)->toBeNull()
        ->and($membresia->invitacion_expira_en)->toBeNull()
        ->and(Hash::check(CLAVE_VALIDA, $usuario->password))->toBeTrue()
        ->and($usuario->email_verified_at)->not->toBeNull();
});

it('rechaza con el mismo mensaje un token desconocido, malformado o vacío', function (string $token) {
    invitar(administradorDe($this->a));

    expect(fn () => aceptar($token))->toThrow(InvitacionInvalida::class, 'La invitación no es válida o ya venció.');
})->with([
    'desconocido' => str_repeat('a', 43),
    'malformado' => 'no es un token',
    'vacío' => '',
]);

it('rechaza un hash usado como token', function () {
    $membresia = invitar(administradorDe($this->a));

    expect(fn () => aceptar($membresia->invitacion_hash))->toThrow(InvitacionInvalida::class);
});

it('rechaza una invitación vencida sin activarla', function () {
    $membresia = invitar(administradorDe($this->a));
    $token = tokenDeLaUltimaInvitacion();

    $this->travel(7)->days();
    $this->travel(1)->second();

    expect(fn () => aceptar($token))->toThrow(InvitacionInvalida::class, 'La invitación no es válida o ya venció.')
        ->and($membresia->fresh()->estado)->toBe('invitada');
});

it('acepta hasta el último segundo de vigencia', function () {
    invitar(administradorDe($this->a));
    $token = tokenDeLaUltimaInvitacion();

    $this->travel(7)->days();
    $this->travel(-1)->second();

    expect(aceptar($token)->estado)->toBe('activa');
});

it('sirve una sola vez', function () {
    invitar(administradorDe($this->a));
    $token = tokenDeLaUltimaInvitacion();
    aceptar($token);

    expect(fn () => aceptar($token, 'OtraClave20261'))->toThrow(InvitacionInvalida::class);
});

it('exige una contraseña de al menos 12 caracteres con letras y números', function (?string $clave, string $mensaje) {
    $membresia = invitar(administradorDe($this->a));
    $token = tokenDeLaUltimaInvitacion();

    try {
        aceptar($token, $clave);
        $this->fail('Se esperaba ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors()['contrasena'])->toContain($mensaje);
    }

    expect($membresia->fresh()->estado)->toBe('invitada')
        ->and(aceptar($token)->estado)->toBe('activa');
})->with([
    'nula' => [null, 'La contraseña es obligatoria.'],
    'corta' => ['Clave2026', 'La contraseña debe tener al menos 12 caracteres.'],
    'sin números' => ['SoloLetrasLargas', 'La contraseña debe tener al menos un número.'],
    'sin letras' => ['123456789012', 'La contraseña debe tener al menos una letra.'],
]);

it('no cambia la contraseña de una cuenta ya establecida y exige iniciar sesión con ella', function (Closure $cuenta) {
    $usuario = $cuenta($this->b);
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador, filaParaInvitar(['correo' => $usuario->correo]));
    $token = tokenDeLaUltimaInvitacion();
    $otro = newUsuario();

    expect(fn () => aceptar($token, CLAVE_VALIDA))
        ->toThrow(InvitacionRequiereSesion::class, 'Esta invitación es para una cuenta que ya existe. Inicie sesión con esa cuenta para aceptarla.')
        ->and(fn () => aceptar($token, CLAVE_VALIDA, $otro))->toThrow(InvitacionRequiereSesion::class)
        ->and($membresia->fresh()->estado)->toBe('invitada');

    aceptar($token, null, $usuario);

    expect($membresia->fresh()->estado)->toBe('activa')
        ->and(Hash::check('password', $usuario->fresh()->password))->toBeTrue();
})->with([
    'con acceso activo en otro establecimiento' => [fn (int $b): Usuario => usuarioConMembresia($b, 'activa')],
    'de soporte de VINKURED' => [fn (): Usuario => newUsuario(['es_soporte_vinkured' => true])],
]);

it('trata como cuenta nueva a quien solo tiene otra invitación pendiente', function () {
    $usuario = usuarioConMembresia($this->b, 'invitada');
    invitar(administradorDe($this->a), filaParaInvitar(['correo' => $usuario->correo]));

    aceptar(tokenDeLaUltimaInvitacion());

    expect(Hash::check(CLAVE_VALIDA, $usuario->fresh()->password))->toBeTrue()
        ->and(DB::table('membresias')->where('usuario_id', $usuario->id)->where('establecimiento_id', $this->b)->value('estado'))->toBe('invitada');
});

it('activa solo la membresía de la invitación', function () {
    $otraAdministradora = administradorDe($this->b);
    invitar($otraAdministradora, filaParaInvitar(['correo' => 'misma@ejemplo.cl']));
    $tokenDeB = tokenDeLaUltimaInvitacion();
    invitar(administradorDe($this->a), filaParaInvitar(['correo' => 'misma@ejemplo.cl']));

    aceptar($tokenDeB);

    expect(Membresia::query()->where('establecimiento_id', $this->b)->where('persona_id', '!=', $otraAdministradora->persona_id)->value('estado'))->toBe('activa')
        ->and(Membresia::query()->where('establecimiento_id', $this->a)->whereNotNull('invitacion_hash')->value('estado'))->toBe('invitada');
});
