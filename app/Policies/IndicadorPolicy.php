<?php

namespace App\Policies;

use App\Models\Indicador;
use App\Models\Usuario;
use App\Support\RolesDelModulo;

/**
 * Catálogo global de indicadores: lo ve cualquier funcionario del establecimiento activo. Solo
 * lectura: cualquier otra habilidad queda denegada por omisión.
 */
class IndicadorPolicy
{
    public function verCualquiera(Usuario $usuario): bool
    {
        return app(RolesDelModulo::class)->esFuncionario($usuario);
    }

    public function ver(Usuario $usuario, Indicador $indicador): bool
    {
        return $this->verCualquiera($usuario);
    }
}
