<?php

namespace App\Exceptions;

use App\Models\Acompanamiento;
use LogicException;

/**
 * Una respuesta referencia un indicador ausente del snapshot de la pauta del acompañamiento.
 */
final class IndicadorFueraDelSnapshot extends LogicException
{
    public static function para(Acompanamiento $acompanamiento, int $indicadorId): self
    {
        return new self("El indicador [{$indicadorId}] no forma parte del snapshot de la pauta del acompañamiento [{$acompanamiento->getKey()}].");
    }
}
