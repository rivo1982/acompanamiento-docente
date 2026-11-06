<?php

use App\Livewire\Acompanamientos\Acompanamientos;
use App\Livewire\Acompanamientos\NuevoAcompanamiento;
use App\Livewire\Acompanamientos\ObservarAcompanamiento;
use App\Livewire\Acompanamientos\VerAcompanamiento;
use App\Livewire\Importaciones\CargaMasiva;
use App\Livewire\Importaciones\ResultadoDeImportacion;
use App\Livewire\Invitaciones\AceptarInvitacion;
use App\Livewire\Invitaciones\InvitacionesPendientes;
use App\Livewire\Invitaciones\NuevaInvitacion;
use App\Livewire\Login;
use App\Livewire\Observadores\Observadores;
use App\Livewire\Pautas\FormularioDePauta;
use App\Livewire\Pautas\Pautas;
use App\Livewire\Pautas\VerPauta;
use App\Livewire\SeleccionarEstablecimiento;
use App\Support\EstablecimientoActual;
use App\Support\Importacion\PlantillaDePlanilla;
use Illuminate\Support\Facades\Route;

// La raíz lleva al inicio provisional (que pide iniciar sesión si hace falta), no a la bienvenida de Laravel.
Route::redirect('/', '/panel');

// Acceso: mismas rutas y nombres que Vinkured (login, logout, establecimientos.seleccionar, panel), para que los
// componentes y layouts copiados (@host-owned) funcionen sin cambios y se reemplacen por los del host al integrar.
Route::get('/login', Login::class)->middleware('guest')->name('login');

// Enlace del correo de invitación (Q18): invitados y usuarios con sesión, sin establecimiento activo. Limitado por enlace e
// IP, con un techo por IP (limitador `invitaciones`, AppServiceProvider), para frenar a quien prueba tokens.
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

        // Invitaciones de funcionarios (Q18): solo el Administrador del establecimiento activo.
        Route::middleware('can:invitar-funcionarios')->group(function () {
            Route::get('/usuarios/invitaciones', InvitacionesPendientes::class)->name('invitaciones.index');
            Route::get('/usuarios/invitaciones/nueva', NuevaInvitacion::class)->name('invitaciones.create');

            // Carga masiva (Q18): subir la planilla, ver las cargas recientes y el resultado de cada una.
            Route::get('/usuarios/importaciones', CargaMasiva::class)->name('importaciones.index');
            Route::get('/usuarios/importaciones/plantilla', fn (PlantillaDePlanilla $plantilla) => $plantilla->descargar())
                ->name('importaciones.plantilla');
            Route::get('/usuarios/importaciones/{importacion}', ResultadoDeImportacion::class)
                ->whereNumber('importacion')
                ->name('importaciones.show');
        });

        // Observadores y sus docentes (T6): solo el administrador del módulo (directivo) del establecimiento activo.
        Route::middleware('can:gestionar-observadores')->group(function () {
            Route::get('/usuarios/observadores', Observadores::class)->name('observadores.index');
        });

        // Pautas del establecimiento (T5): cada componente exige PautaPolicy al abrir y en cada acción (ver para cualquier
        // funcionario; crear, editar y eliminar solo el administrador del módulo). Una pauta de otro establecimiento da 404.
        Route::get('/pautas', Pautas::class)->name('pautas.index');
        Route::get('/pautas/nueva', FormularioDePauta::class)->name('pautas.create');
        Route::get('/pautas/{pauta}/editar', FormularioDePauta::class)->name('pautas.edit');
        Route::get('/pautas/{pauta}', VerPauta::class)->name('pautas.show');

        // Acompañamientos de aula (T7): cada componente exige AcompanamientoPolicy al abrir y en cada acción (la lista,
        // a cualquier funcionario; iniciar, a un observador; observar, solo a su observador mientras sigue en curso).
        Route::get('/acompanamientos', Acompanamientos::class)->name('acompanamientos.index');
        Route::get('/acompanamientos/nuevo', NuevoAcompanamiento::class)->name('acompanamientos.create');
        Route::get('/acompanamientos/{acompanamiento}/observar', ObservarAcompanamiento::class)
            ->whereNumber('acompanamiento')
            ->name('acompanamientos.edit');

        // Retroalimentación de un acompañamiento (T7): AcompanamientoPolicy::ver (su observador, su docente una vez
        // finalizado y el administrador del módulo). El aviso al docente (AcompanamientoFinalizado::RUTA) enlaza aquí.
        Route::get('/acompanamientos/{acompanamiento}', VerAcompanamiento::class)
            ->whereNumber('acompanamiento')
            ->name('acompanamientos.show');
    });
});
