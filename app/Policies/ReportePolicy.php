<?php

namespace App\Policies;

use App\Enums\EstadoAcompanamiento;
use App\Models\Acompanamiento;
use App\Models\Funcionario;
use App\Models\Usuario;
use App\Support\RolesDelModulo;

/**
 * Reportes del módulo (6.1–6.4), registrados como gates en AppServiceProvider. El acceso de nivel
 * sostenedor no está modelado (pregunta abierta): queda denegado.
 */
class ReportePolicy
{
    /**
     * Gate `ver-reportes-establecimiento`: reportes del establecimiento activo.
     */
    public function verReportesEstablecimiento(Usuario $usuario): bool
    {
        return app(RolesDelModulo::class)->esAdministrador($usuario);
    }

    /**
     * Gate `ver-reporte-docente` (6.4): el propio docente, el administrador del módulo y quien es
     * HOY observador del establecimiento y tiene un acompañamiento finalizado de ese docente. Decisión
     * del usuario: el ex observador pierde el acceso.
     */
    public function verReporteDocente(Usuario $usuario, Funcionario $docente): bool
    {
        $roles = app(RolesDelModulo::class);

        if (! $roles->esDelEstablecimientoActivo($docente->establecimiento_id)) {
            return false;
        }

        return $roles->esFuncionarioPropio($usuario, $docente->getKey())
            || $roles->esAdministrador($usuario)
            || ($roles->esObservador($usuario) && Acompanamiento::query()
                ->whereIn('observador_id', $roles->funcionarios())
                ->where('docente_id', $docente->getKey())
                ->where('estado', EstadoAcompanamiento::Finalizado->value)
                ->exists());
    }
}
