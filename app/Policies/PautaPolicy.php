<?php

namespace App\Policies;

use App\Models\Pauta;
use App\Models\Usuario;
use App\Support\RolesDelModulo;

/**
 * Pautas del establecimiento activo: cualquier funcionario las ve y exporta; solo el administrador
 * del módulo las crea, modifica y elimina. Una pauta de otro establecimiento siempre es no.
 */
class PautaPolicy
{
    public function verCualquiera(Usuario $usuario): bool
    {
        return $this->roles()->esFuncionario($usuario);
    }

    public function ver(Usuario $usuario, Pauta $pauta): bool
    {
        return $this->roles()->esDelEstablecimientoActivo($pauta->establecimiento_id)
            && $this->verCualquiera($usuario);
    }

    public function exportar(Usuario $usuario, Pauta $pauta): bool
    {
        return $this->ver($usuario, $pauta);
    }

    public function crear(Usuario $usuario): bool
    {
        return $this->roles()->esAdministrador($usuario);
    }

    public function actualizar(Usuario $usuario, Pauta $pauta): bool
    {
        return $this->roles()->esDelEstablecimientoActivo($pauta->establecimiento_id)
            && $this->roles()->esAdministrador($usuario);
    }

    public function eliminar(Usuario $usuario, Pauta $pauta): bool
    {
        return $this->actualizar($usuario, $pauta);
    }

    private function roles(): RolesDelModulo
    {
        return app(RolesDelModulo::class);
    }
}
