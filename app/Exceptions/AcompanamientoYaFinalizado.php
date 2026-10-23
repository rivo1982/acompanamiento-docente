<?php

namespace App\Exceptions;

use App\Models\Acompanamiento;
use LogicException;

/**
 * Se intentó finalizar un acompañamiento que ya estaba cerrado.
 */
final class AcompanamientoYaFinalizado extends LogicException
{
    public static function para(Acompanamiento $acompanamiento): self
    {
        return new self("El acompañamiento [{$acompanamiento->getKey()}] ya está finalizado.");
    }
}
