<?php

/*
 * Wiring that mirrors Vinkured's AppServiceProvider, bootstrap/app.php and config/auth.php.
 */

use App\Http\Middleware\EstablecimientoActivo;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use App\Support\RolesDelUsuario;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('registra EstablecimientoActual y RolesDelUsuario como una instancia por petición', function (string $clase) {
    $primera = app($clase);

    expect(app($clase))->toBe($primera);

    app()->forgetScopedInstances();

    expect(app($clase))->not->toBe($primera);
})->with([EstablecimientoActual::class, RolesDelUsuario::class]);

it('comparte el establecimiento de paraTrabajo con cualquier resolución dentro del trabajo', function () {
    $id = app(EstablecimientoActual::class)->paraTrabajo(42, fn () => app(EstablecimientoActual::class)->id());

    expect($id)->toBe(42)
        ->and(app(EstablecimientoActual::class)->id())->toBeNull();
});

it('registra el alias de middleware establecimiento.activo', function () {
    expect(app(Kernel::class)->getMiddlewareAliases()['establecimiento.activo'] ?? null)->toBe(EstablecimientoActivo::class);
});

it('autentica con el modelo Usuario del anfitrión', function () {
    expect(config('auth.providers.users.model'))->toBe(Usuario::class)
        ->and(Auth::createUserProvider('users')->getModel())->toBe(Usuario::class);
});
