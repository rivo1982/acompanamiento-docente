<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El enlace de invitación no sirve: desconocido, malformado, vencido, anulado, reemplazado o ya usado. Un solo
 * mensaje para todos los casos, sin detalle, para no orientar a quien prueba enlaces.
 */
final class InvitacionInvalida extends RuntimeException
{
    public static function generica(): self
    {
        return new self('La invitación no es válida o ya venció.');
    }
}
