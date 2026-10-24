<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * El texto ingresado no es un RUT válido. Los mensajes son para el personal y se pueden mostrar tal cual.
 */
final class RutInvalido extends InvalidArgumentException
{
    public static function vacio(): self
    {
        return new self('El RUT es obligatorio.');
    }

    public static function formatoInvalido(): self
    {
        return new self('El RUT no tiene un formato válido.');
    }

    public static function digitoVerificadorIncorrecto(): self
    {
        return new self('El dígito verificador del RUT no corresponde.');
    }
}
