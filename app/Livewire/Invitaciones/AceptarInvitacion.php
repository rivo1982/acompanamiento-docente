<?php

namespace App\Livewire\Invitaciones;

use App\Actions\AceptarInvitacion as AccionAceptarInvitacion;
use App\Exceptions\InvitacionInvalida;
use App\Exceptions\InvitacionRequiereSesion;
use App\Models\Membresia;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use App\Support\Invitaciones\ReglasDeContrasena;
use App\Support\Invitaciones\TokenDeInvitacion;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pantalla del enlace de invitación (Q18, ruta `invitaciones.aceptar`). Clase nueva del módulo para entregar a
 * Vinkured. La abren invitados y usuarios con sesión; no exige establecimiento activo.
 *
 * Estados: enlace inservible (un solo mensaje genérico, sin formulario), cuenta nueva (contraseña y confirmación),
 * cuenta ya establecida sin sesión (pide iniciar sesión), misma cuenta con sesión (un botón), otra cuenta con sesión
 * (pide cerrarla) y aceptada. Toda la aceptación la hace la acción App\Actions\AceptarInvitacion; esta clase solo
 * anticipa el estado para mostrar la pantalla correcta, sin consumir el enlace.
 *
 * El token en texto plano nunca va al HTML ni al snapshot de Livewire: se guarda cifrado con la clave de la app en
 * una propiedad bloqueada, y solo se descifra en memoria al aceptar. La URL de la página sí lo lleva (es el enlace
 * del correo), por eso la respuesta envía `Referrer-Policy: no-referrer` y `Cache-Control: no-store`.
 */
#[Layout('components.layouts.simple', ['titulo' => 'Aceptar invitación'])]
class AceptarInvitacion extends Component
{
    public const INVALIDA = 'invalida';

    public const NUEVA_CONTRASENA = 'nueva_contrasena';

    public const REQUIERE_SESION = 'requiere_sesion';

    public const CONFIRMAR = 'confirmar';

    public const OTRA_CUENTA = 'otra_cuenta';

    public const ACEPTADA = 'aceptada';

    #[Locked]
    public string $tokenCifrado = '';

    #[Locked]
    public string $estado = self::INVALIDA;

    /** Nombre del establecimiento que invita (ya viene en el correo); nunca otros establecimientos de la cuenta. */
    #[Locked]
    public ?string $establecimiento = null;

    public string $contrasena = '';

    public string $contrasena_confirmation = '';

    public function mount(string $token): void
    {
        $this->tokenCifrado = Crypt::encryptString($token);
        $this->estado = $this->estadoPara($token);
    }

    public function aceptar(AccionAceptarInvitacion $accion, EstablecimientoActual $actual): void
    {
        if (! in_array($this->estado, [self::NUEVA_CONTRASENA, self::CONFIRMAR], true)) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->mostrarInvalida();

            return;
        }

        /** @var Usuario|null $autenticado */
        $autenticado = Auth::user();

        try {
            if ($this->estado === self::NUEVA_CONTRASENA) {
                $this->validarContrasena();
            }

            $membresia = $accion->aceptar($token, $this->estado === self::NUEVA_CONTRASENA ? $this->contrasena : null, $autenticado);
        } catch (ValidationException $e) {
            $this->reset('contrasena', 'contrasena_confirmation');
            $this->js("document.getElementById('contrasena').focus()");

            throw $e;
        } catch (InvitacionInvalida) {
            $this->mostrarInvalida();

            return;
        } catch (InvitacionRequiereSesion) {
            $this->reset('contrasena', 'contrasena_confirmation');
            $this->estado = self::REQUIERE_SESION;
            $this->js("document.getElementById('estado-invitacion').focus()");

            return;
        }

        $this->reset('contrasena', 'contrasena_confirmation');

        if ($autenticado !== null) {
            $actual->olvidar();
            $this->redirectRoute($actual->seleccionar($membresia->establecimiento_id) ? 'panel' : 'establecimientos.seleccionar');

            return;
        }

        $this->estado = self::ACEPTADA;
        $this->js("document.getElementById('estado-invitacion').focus()");
    }

    public function render(): View
    {
        return view('livewire.invitaciones.aceptar-invitacion', [
            'reglaDeContrasena' => 'Mínimo 12 caracteres, con letras y números.',
        ])->response(function (Response $respuesta): void {
            $respuesta->headers->set('Referrer-Policy', 'no-referrer');
            $respuesta->headers->set('Cache-Control', 'no-store, private');
        });
    }

    /**
     * Anticipa el estado sin consumir el enlace: los mismos criterios de vigencia y de credencial establecida que la
     * acción (que vuelve a comprobarlos bajo bloqueo al aceptar).
     */
    private function estadoPara(string $token): string
    {
        if (! TokenDeInvitacion::tieneFormato($token)) {
            return self::INVALIDA;
        }

        $membresia = Membresia::query()
            ->where('invitacion_hash', TokenDeInvitacion::hash($token))
            ->where('estado', 'invitada')
            ->where('invitacion_expira_en', '>=', now())
            ->with(['usuario', 'establecimiento'])
            ->first();

        if ($membresia === null || $membresia->usuario === null) {
            return self::INVALIDA;
        }

        /** @var Usuario|null $autenticado */
        $autenticado = Auth::user();

        if ($autenticado !== null && (int) $autenticado->getKey() !== (int) $membresia->usuario_id) {
            return self::OTRA_CUENTA;
        }

        $this->establecimiento = $membresia->establecimiento?->nombre;

        if (! $this->tieneCredencialEstablecida($membresia->usuario, $membresia)) {
            return self::NUEVA_CONTRASENA;
        }

        return $autenticado === null ? self::REQUIERE_SESION : self::CONFIRMAR;
    }

    /** Mismo criterio que la acción: cuenta de soporte, u otra membresía activa. */
    private function tieneCredencialEstablecida(Usuario $usuario, Membresia $invitacion): bool
    {
        return $usuario->es_soporte_vinkured
            || $usuario->membresias()
                ->whereKeyNot($invitacion->getKey())
                ->where('estado', Membresia::ESTADO_ACTIVA)
                ->exists();
    }

    /**
     * Las reglas de ReglasDeContrasena más la confirmación, juntas para mostrar todos los errores de una vez. La
     * acción vuelve a validar la contraseña.
     *
     * @throws ValidationException
     */
    private function validarContrasena(): void
    {
        $this->validate([
            ReglasDeContrasena::CAMPO => ['required', 'string', 'max:'.ReglasDeContrasena::MAXIMO, Password::defaults(), 'confirmed'],
        ], [
            ...ReglasDeContrasena::MENSAJES,
            'contrasena.confirmed' => 'Las contraseñas no coinciden.',
        ]);
    }

    private function token(): ?string
    {
        try {
            return Crypt::decryptString($this->tokenCifrado);
        } catch (DecryptException) {
            return null;
        }
    }

    private function mostrarInvalida(): void
    {
        $this->reset('contrasena', 'contrasena_confirmation');
        $this->estado = self::INVALIDA;
        $this->establecimiento = null;
        $this->js("document.getElementById('estado-invitacion').focus()");
    }
}
