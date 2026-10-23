<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se intentó guardar un modelo con un establecimiento distinto del activo
 * (al crearlo, o al mover una fila existente a otro establecimiento).
 *
 * @host-owned Copied from Vinkured app/Exceptions/EstablecimientoAjeno.php — do not modify here; replace with the host class at integration.
 */
final class EstablecimientoAjeno extends RuntimeException
{
    public static function para(string $modelo): self
    {
        return new self("{$modelo} no puede guardarse con un establecimiento distinto del activo.");
    }
}
