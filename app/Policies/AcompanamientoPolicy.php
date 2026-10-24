<?php

namespace App\Policies;

use App\Models\Acompanamiento;
use App\Models\AsignacionAcompanamiento;
use App\Models\Funcionario;
use App\Models\Usuario;
use App\Support\RolesDelModulo;

/**
 * Acompañamientos del establecimiento activo:
 * - crear: un observador vigente, para un docente vigente que tiene asignado;
 * - actualizar, responder y finalizar: su observador, mientras sigue en curso;
 * - ver y exportar: su observador, su docente solo una vez finalizado y el administrador del módulo.
 * Un acompañamiento de otro establecimiento siempre es no.
 */
class AcompanamientoPolicy
{
    public function crear(Usuario $usuario, Funcionario $docente): bool
    {
        $roles = $this->roles();

        return $roles->esDelEstablecimientoActivo($docente->establecimiento_id)
            && $roles->esObservador($usuario)
            && $roles->puedeSerObservado((int) $docente->getKey())
            && AsignacionAcompanamiento::query()
                ->whereIn('observador_id', $roles->funcionarios())
                ->where('docente_id', $docente->getKey())
                ->exists();
    }

    public function actualizar(Usuario $usuario, Acompanamiento $acompanamiento): bool
    {
        return $this->estaEnCursoYLoObserva($usuario, $acompanamiento);
    }

    public function responder(Usuario $usuario, Acompanamiento $acompanamiento): bool
    {
        return $this->estaEnCursoYLoObserva($usuario, $acompanamiento);
    }

    public function finalizar(Usuario $usuario, Acompanamiento $acompanamiento): bool
    {
        return $this->estaEnCursoYLoObserva($usuario, $acompanamiento);
    }

    public function ver(Usuario $usuario, Acompanamiento $acompanamiento): bool
    {
        $roles = $this->roles();

        if (! $roles->esDelEstablecimientoActivo($acompanamiento->establecimiento_id)) {
            return false;
        }

        if ($roles->esFuncionarioPropio($usuario, $acompanamiento->observador_id)) {
            return true;
        }

        if ($roles->esFuncionarioPropio($usuario, $acompanamiento->docente_id)) {
            return $acompanamiento->estaFinalizado();
        }

        return $roles->esAdministrador($usuario);
    }

    public function exportar(Usuario $usuario, Acompanamiento $acompanamiento): bool
    {
        return $this->ver($usuario, $acompanamiento);
    }

    private function estaEnCursoYLoObserva(Usuario $usuario, Acompanamiento $acompanamiento): bool
    {
        $roles = $this->roles();

        return ! $acompanamiento->estaFinalizado()
            && $roles->esDelEstablecimientoActivo($acompanamiento->establecimiento_id)
            && $roles->esFuncionarioPropio($usuario, $acompanamiento->observador_id);
    }

    private function roles(): RolesDelModulo
    {
        return app(RolesDelModulo::class);
    }
}
