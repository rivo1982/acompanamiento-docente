<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Models\Membresia;
use App\Models\Usuario;
use Closure;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Establecimiento activo de la petición.
 *
 * El id vive en la sesión, pero la sesión nunca es la fuente de verdad: cada
 * resolución lo contrasta con una membresía activa, con establecimiento, del
 * usuario autenticado. Un valor ajeno, manipulado o de una membresía ya
 * suspendida resuelve a null. Se registra como "scoped": una instancia por
 * petición; el middleware `establecimiento.activo` la refresca en cada una.
 *
 * @host-owned Copied from Vinkured app/Support/EstablecimientoActual.php — do not modify here; replace with the host class at integration.
 */
final class EstablecimientoActual
{
    public const CLAVE_SESION = 'establecimiento_id';

    private bool $resuelta = false;

    private ?Membresia $membresia = null;

    /** Establecimiento fijado por paraTrabajo(), o null fuera de un trabajo en cola. */
    private ?int $deTrabajo = null;

    public function id(): ?int
    {
        return $this->deTrabajo ?? $this->membresia()?->establecimiento_id;
    }

    public function establecimiento(): ?Establecimiento
    {
        return $this->deTrabajo !== null
            ? Establecimiento::query()->find($this->deTrabajo)
            : $this->membresia()?->establecimiento;
    }

    /**
     * Dentro de un trabajo en cola NO hay membresía: sin usuario no hay roles ni permisos (falla cerrado).
     */
    public function membresia(): ?Membresia
    {
        if ($this->deTrabajo !== null) {
            return null;
        }

        return $this->resuelta ? $this->membresia : $this->refrescar();
    }

    /**
     * ÚNICA vía autorizada para que el trabajo en cola (jobs, comandos) use los modelos filtrados: fija
     * $establecimientoId como establecimiento activo SOLO mientras dura $trabajo y restaura el estado
     * anterior en `finally`, también si lanza, para que nunca se filtre al siguiente trabajo ni a la
     * petición que lo ejecuta (cola `sync`). No hay usuario ni membresía dentro: los roles y las políticas
     * responden que no. El id viene del trabajo, nunca de la sesión.
     *
     * @template T
     *
     * @param  Closure(): T  $trabajo
     * @return T
     */
    public function paraTrabajo(int $establecimientoId, Closure $trabajo): mixed
    {
        if ($establecimientoId < 1) {
            throw new InvalidArgumentException('El establecimiento de un trabajo debe ser un id positivo.');
        }

        $anterior = $this->deTrabajo;
        $this->deTrabajo = $establecimientoId;

        try {
            return $trabajo();
        } finally {
            $this->deTrabajo = $anterior;
        }
    }

    /**
     * Vuelve a consultar la base y devuelve la membresía vigente, o null.
     */
    public function refrescar(): ?Membresia
    {
        $this->resuelta = true;
        $usuario = Auth::user();
        $id = $this->idEnSesion();

        if (! $usuario instanceof Usuario || $id === null) {
            return $this->membresia = null;
        }

        return $this->membresia = $usuario->membresiasConAcceso()
            ->where('establecimiento_id', $id)
            ->with('establecimiento')
            ->first();
    }

    /**
     * Fija el establecimiento activo solo si el usuario autenticado tiene una
     * membresía activa en él. Devuelve false (sin tocar la sesión) si no.
     */
    public function seleccionar(int|string $establecimientoId): bool
    {
        $usuario = Auth::user();

        if (! $usuario instanceof Usuario || ! self::esId($establecimientoId)) {
            return false;
        }

        $membresia = $usuario->membresiasConAcceso()
            ->where('establecimiento_id', (int) $establecimientoId)
            ->with('establecimiento')
            ->first();

        if ($membresia === null) {
            return false;
        }

        session()->put(self::CLAVE_SESION, $membresia->establecimiento_id);
        $this->resuelta = true;
        $this->membresia = $membresia;

        return true;
    }

    /**
     * Borra el establecimiento activo de la sesión y de la memoria.
     */
    public function olvidar(): void
    {
        session()->forget(self::CLAVE_SESION);
        $this->resuelta = false;
        $this->membresia = null;
    }

    /**
     * Cierra la sesión: desautentica, invalida la sesión (nuevo id) y rota el token CSRF.
     */
    public function cerrarSesion(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->resuelta = false;
        $this->membresia = null;
    }

    private function idEnSesion(): ?int
    {
        $valor = session()->get(self::CLAVE_SESION);

        return self::esId($valor) ? (int) $valor : null;
    }

    private static function esId(mixed $valor): bool
    {
        return (is_int($valor) && $valor > 0)
            || (is_string($valor) && ctype_digit($valor) && strlen($valor) <= 18 && (int) $valor > 0);
    }
}
