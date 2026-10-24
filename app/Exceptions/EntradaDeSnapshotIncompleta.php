<?php

namespace App\Exceptions;

use App\Models\Acompanamiento;
use LogicException;

/**
 * El indicador sí forma parte del snapshot de la pauta, pero a su entrada le faltan claves sin
 * las cuales no hay retroalimentación automática.
 */
final class EntradaDeSnapshotIncompleta extends LogicException
{
    /**
     * @param  list<string>  $clavesFaltantes
     */
    public static function para(Acompanamiento $acompanamiento, int $indicadorId, array $clavesFaltantes): self
    {
        $claves = implode(', ', $clavesFaltantes);

        return new self("Al indicador [{$indicadorId}] del snapshot del acompañamiento [{$acompanamiento->getKey()}] le falta [{$claves}].");
    }
}
