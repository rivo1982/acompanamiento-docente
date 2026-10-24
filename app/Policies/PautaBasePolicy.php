<?php

namespace App\Policies;

use App\Models\PautaBase;
use App\Models\Usuario;
use App\Support\RolesDelModulo;

/**
 * Catálogo global de pautas base: lo ve cualquier funcionario del establecimiento activo. Solo
 * lectura: cualquier otra habilidad queda denegada por omisión.
 */
class PautaBasePolicy
{
    public function verCualquiera(Usuario $usuario): bool
    {
        return app(RolesDelModulo::class)->esFuncionario($usuario);
    }

    public function ver(Usuario $usuario, PautaBase $pautaBase): bool
    {
        return $this->verCualquiera($usuario);
    }
}
