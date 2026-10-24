<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * El archivo subido no se puede leer como planilla .xlsx. Los mensajes son para el personal y no incluyen la ruta del
 * archivo ni detalles internos; la causa técnica queda en la excepción anterior.
 */
final class PlanillaIlegible extends RuntimeException
{
    public static function noEsXlsx(): self
    {
        return new self('El archivo debe ser una planilla Excel (.xlsx).');
    }

    public static function protegidaOAntigua(): self
    {
        return new self('La planilla está protegida con contraseña o en un formato antiguo de Excel. Quitar la contraseña y guardarla como .xlsx.');
    }

    public static function noSePuedeLeer(?Throwable $causa = null): self
    {
        return new self('No se pudo leer la planilla. Guardarla de nuevo como .xlsx desde Excel y volver a subirla.', 0, $causa);
    }
}
