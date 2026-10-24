<?php

namespace App\Exceptions;

use App\Models\Acompanamiento;
use LogicException;

/**
 * Se intentó finalizar, o cambiar las respuestas de, un acompañamiento que ya estaba cerrado.
 */
final class AcompanamientoYaFinalizado extends LogicException
{
    public static function para(Acompanamiento $acompanamiento): self
    {
        return new self("El acompañamiento [{$acompanamiento->getKey()}] ya está finalizado.");
    }

    public static function paraRespuestas(Acompanamiento $acompanamiento): self
    {
        return new self("El acompañamiento [{$acompanamiento->getKey()}] ya está finalizado y no admite cambios en sus respuestas.");
    }
}
