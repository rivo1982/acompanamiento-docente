<?php

namespace App\Exceptions;

use LogicException;

/**
 * Se intentó iniciar un acompañamiento con una pauta inexistente, eliminada o de otro
 * establecimiento.
 */
final class PautaNoDisponible extends LogicException
{
    public static function para(mixed $pautaId): self
    {
        $id = is_scalar($pautaId) ? (string) $pautaId : 'sin id';

        return new self("La pauta [{$id}] no existe o no está disponible en el establecimiento activo.");
    }
}
