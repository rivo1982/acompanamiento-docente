<?php

namespace App\Livewire;

use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** @host-owned Copied from Vinkured app/Livewire/Login.php — do not modify here; replace with the host class at integration. */
#[Layout('components.layouts.simple', ['titulo' => 'Iniciar sesión'])]
class Login extends Component
{
    /**
     * Piso de tiempo de la comprobación de credenciales: 200 ms, el valor por defecto
     * de SessionGuard (`timeboxDuration`) en Laravel 13. No se midió la latencia real.
     */
    public const PISO_TIEMPO_MICROSEGUNDOS = 200_000;

    private const MAX_INTENTOS = 5;

    private const VENTANA_SEGUNDOS = 60;

    private static ?string $hashFicticio = null;

    public string $correo = '';

    public string $contrasena = '';

    public function iniciarSesion(EstablecimientoActual $actual): void
    {
        $this->correo = Str::lower(trim($this->correo));

        $this->validate([
            'correo' => ['required', 'string', 'email', 'max:255'],
            'contrasena' => ['required', 'string', 'max:1024'],
        ], [], ['correo' => 'correo', 'contrasena' => 'contraseña']);

        $clave = $this->correo.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $this->reset('contrasena');
            $segundos = RateLimiter::availableIn($clave);
            $this->addError('bloqueo', 'Demasiados intentos. Intentar de nuevo en '.trans_choice('{1} :count segundo|[2,*] :count segundos', $segundos).'.');
            $this->js("document.getElementById('alerta-login').focus()");

            return;
        }

        if (! $this->credencialesValidas()) {
            RateLimiter::hit($clave, self::VENTANA_SEGUNDOS);
            $this->reset('contrasena');
            $this->addError('credenciales', 'Las credenciales no coinciden.');
            $this->js("document.getElementById('contrasena').focus()");

            return;
        }

        RateLimiter::clear($clave);
        $this->reset('contrasena');

        // Auth::attempt ya regeneró el id de sesión (anti fijación). Se parte sin contexto previo.
        $actual->olvidar();

        /** @var Usuario $usuario */
        $usuario = Auth::user();
        $accesos = $usuario->membresiasConAcceso()->pluck('establecimiento_id');

        if ($accesos->isEmpty()) {
            // cerrarSesion() invalida la sesión y rota el token CSRF; la página renderizada
            // conservaría el token viejo. Se navega por completo a GET /login, que trae el
            // token nuevo, y el aviso viaja en un flash de la sesión nueva.
            $actual->cerrarSesion();
            session()->flash('aviso', 'Esta cuenta no tiene acceso activo a ningún establecimiento. Contactar al Administrador del establecimiento.');
            $this->redirectRoute('login');

            return;
        }

        if ($accesos->count() === 1) {
            $actual->seleccionar($accesos->first());
            $this->redirectRoute('panel');

            return;
        }

        $this->redirectRoute('establecimientos.seleccionar');
    }

    public function render(): View
    {
        return view('livewire.login');
    }

    /**
     * Toda la comprobación (correo desconocido con hash ficticio, o Auth::attempt)
     * corre dentro de un único Timebox con el mismo piso, de modo que ambas ramas
     * tarden al menos lo mismo por construcción.
     */
    private function credencialesValidas(): bool
    {
        return app(Timebox::class)->call(function (): bool {
            if (! Usuario::query()->where('correo', $this->correo)->exists()) {
                Hash::check($this->contrasena, self::$hashFicticio ??= Hash::make(Str::random(40)));

                return false;
            }

            return Auth::attempt(['correo' => $this->correo, 'password' => $this->contrasena]);
        }, self::PISO_TIEMPO_MICROSEGUNDOS);
    }
}
