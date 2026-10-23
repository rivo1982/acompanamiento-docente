<?php

namespace App\Exceptions;

use App\Models\Acompanamiento;
use LogicException;

/**
 * Una respuesta referencia un indicador ausente, o incompleto, en el snapshot de la pauta del acompañamiento.
 */
final class IndicadorFueraDelSnapshot extends LogicException
{
    public static function para(Acompanamiento $acompanamiento, int $indicadorId): self
    {
        return new self("El indicador [{$indicadorId}] no forma parte del snapshot de la pauta del acompañamiento [{$acompanamiento->getKey()}].");
    }

    /**
     * @param  list<string>  $clavesFaltantes
     */
    public static function incompleto(Acompanamiento $acompanamiento, int $indicadorId, array $clavesFaltantes): self
    {
        $claves = implode(', ', $clavesFaltantes);

        return new self("Al indicador [{$indicadorId}] del snapshot del acompañamiento [{$acompanamiento->getKey()}] le falta [{$claves}].");
    }
}
