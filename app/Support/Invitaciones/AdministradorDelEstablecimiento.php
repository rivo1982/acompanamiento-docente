<?php

namespace App\Support\Invitaciones;

use App\Models\Membresia;
use App\Support\EstablecimientoActual;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Autoriza las acciones de invitación por la membresía del Administrador que las pide, no por la sesión: así sirven
 * igual desde una petición (gate `invitar-funcionarios`) y desde un trabajo en cola de la carga masiva, donde no
 * hay usuario. La membresía se relee de la base: debe seguir activa, ser de Administrador y pertenecer al
 * establecimiento activo (el de la sesión o el de EstablecimientoActual::paraTrabajo).
 */
final class AdministradorDelEstablecimiento
{
    public function __construct(private readonly EstablecimientoActual $actual) {}

    /**
     * @return int id del establecimiento activo
     *
     * @throws AuthorizationException
     */
    public function exigir(Membresia $administrador): int
    {
        $establecimientoId = $this->actual->id();

        $vigente = $establecimientoId !== null && Membresia::query()
            ->whereKey($administrador->getKey())
            ->conAcceso()
            ->where('establecimiento_id', $establecimientoId)
            ->where('es_administrador', true)
            ->exists();

        if (! $vigente) {
            throw new AuthorizationException;
        }

        return $establecimientoId;
    }
}
