<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revalida en CADA petición que el establecimiento activo siga respaldado por
 * una membresía activa del usuario, de modo que una suspensión rige de inmediato.
 *
 * @host-owned Copied from Vinkured app/Http/Middleware/EstablecimientoActivo.php — do not modify here; replace with the host class at integration.
 */
class EstablecimientoActivo
{
    public function __construct(private readonly EstablecimientoActual $actual) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            return redirect()->route('login');
        }

        if ($this->actual->refrescar() !== null) {
            return $next($request);
        }

        $this->actual->olvidar();

        if ($usuario->membresiasConAcceso()->exists()) {
            return redirect()->route('establecimientos.seleccionar');
        }

        $this->actual->cerrarSesion();

        return redirect()->route('login')->with('aviso', 'Esta cuenta ya no tiene acceso activo a ningún establecimiento. Contactar al Administrador del establecimiento.');
    }
}
