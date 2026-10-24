<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El trabajo de envío no puede enviar la invitación y reintentar no lo arregla (no hay membresía en el
 * establecimiento del trabajo, o falta su cuenta). Falla sin reintentos. Mensajes sin datos personales.
 */
final class InvitacionImposible extends RuntimeException
{
    public static function sinRegistro(): self
    {
        return new self('No existe la invitación en el establecimiento del trabajo.');
    }
}
