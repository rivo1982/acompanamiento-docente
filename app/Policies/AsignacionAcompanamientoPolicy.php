<?php

namespace App\Policies;

use App\Models\AsignacionAcompanamiento;
use App\Models\Usuario;
use App\Support\RolesDelModulo;

/**
 * Asignaciones observador → docente: solo el administrador del módulo, en su establecimiento activo,
 * y crear solo un par elegible (R1-001).
 */
class AsignacionAcompanamientoPolicy
{
    public function verCualquiera(Usuario $usuario): bool
    {
        return $this->roles()->esAdministrador($usuario);
    }

    public function crear(Usuario $usuario, int $observadorId, int $docenteId): bool
    {
        return $this->roles()->esAdministrador($usuario)
            && AsignacionAcompanamiento::esParElegible($observadorId, $docenteId);
    }

    public function eliminar(Usuario $usuario, AsignacionAcompanamiento $asignacion): bool
    {
        return $this->roles()->esDelEstablecimientoActivo($asignacion->establecimiento_id)
            && $this->roles()->esAdministrador($usuario);
    }

    private function roles(): RolesDelModulo
    {
        return app(RolesDelModulo::class);
    }
}
