<?php

use App\Livewire\Invitaciones\AceptarInvitacion;
use App\Livewire\Login;
use App\Livewire\SeleccionarEstablecimiento;
use App\Support\EstablecimientoActual;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Acceso: mismas rutas y nombres que Vinkured (login, logout, establecimientos.seleccionar, panel), para que los
// componentes y layouts copiados (@host-owned) funcionen sin cambios y se reemplacen por los del host al integrar.
Route::get('/login', Login::class)->middleware('guest')->name('login');

// Enlace del correo de invitación (Q18): invitados y usuarios con sesión, sin establecimiento activo. Limitado por IP
// (limitador `invitaciones`, AppServiceProvider) para frenar a quien prueba tokens.
Route::get('/invitaciones/{token}', AceptarInvitacion::class)
    ->middleware('throttle:invitaciones')
    ->name('invitaciones.aceptar');

Route::middleware('auth')->group(function () {
    Route::post('/logout', function (EstablecimientoActual $actual) {
        $actual->cerrarSesion();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/establecimientos/seleccionar', SeleccionarEstablecimiento::class)
        ->name('establecimientos.seleccionar');

    Route::middleware('establecimiento.activo')->group(function () {
        // Inicio provisional del módulo. En Vinkured `panel` es su propia pantalla; al integrar, esta ruta se quita.
        Route::view('/panel', 'inicio')->name('panel');
    });
});
