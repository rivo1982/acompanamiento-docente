<?php

namespace App\Exceptions;

use LogicException;

/**
 * Una asignación observador → docente cuyo par no es elegible en el establecimiento activo.
 */
final class AsignacionAcompanamientoInvalida extends LogicException
{
    public static function mismoFuncionario(int $funcionarioId): self
    {
        return new self("El funcionario [{$funcionarioId}] no puede asignarse a sí mismo.");
    }

    public static function observadorNoHabilitado(int $funcionarioId): self
    {
        return new self("El funcionario [{$funcionarioId}] no es observador vigente del establecimiento activo.");
    }

    public static function docenteNoHabilitado(int $funcionarioId): self
    {
        return new self("El funcionario [{$funcionarioId}] no califica como docente vigente del establecimiento activo.");
    }
}
